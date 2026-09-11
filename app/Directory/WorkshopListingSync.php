<?php

namespace App\Directory;

use App\Models\Service;
use App\Models\ServiceShop;
use App\Models\VehicleMake;
use App\Models\Workshop;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Keeps the public workshop directory in step with the national registry.
 *
 * The registry is evidence — what RAR, OpenStreetMap and the workshops' own sites say — and every
 * import rebuilds it. A listing is what customers see and what the shop curates on top: a
 * description, photographs, hours, prices, promotion, appointments. So a listing is created from
 * a workshop and linked to it; the fields the registry knows keep following it; and a field an
 * admin changes by hand stops following it (registry_locked), so no import undoes an edit.
 *
 * Only data from sources cleared for publication is used, the same rule as the public export. A
 * workshop is listed when such a source knows it, it holds a RAR authorisation, and there is a
 * phone number to call.
 */
class WorkshopListingSync
{
    /** Listing fields the registry fills, with the name an admin knows them by. */
    public const FIELDS = [
        'name' => 'denumire',
        'county' => 'județ',
        'city' => 'oraș',
        'address' => 'adresă',
        'postal_code' => 'cod poștal',
        'latitude' => 'latitudine',
        'longitude' => 'longitudine',
        'phone' => 'telefon',
        'email' => 'email',
        'website' => 'website',
        'specialities' => 'specializări',
        'certifications' => 'certificări',
        'makes' => 'mărci',
        'services' => 'lucrări',
        'status' => 'stare',
    ];

    /**
     * Registry service key => the catalogue jobs it vouches for, by name.
     *
     * Only where the one plainly includes the other: RAR authorising brake repair covers a pad
     * change, but "general repair" covers nothing a customer could book, so keys like it map to
     * nothing on purpose. ITP is a certification here rather than a job.
     */
    public const SERVICE_MAP = [
        'maintenance' => ['Schimb ulei și filtru ulei', 'Revizie completă'],
        'fuel_injection' => ['Curățare injectoare'],
        'manual_transmission' => ['Schimb ambreiaj', 'Schimb ulei cutie de viteze'],
        'automatic_transmission' => ['Schimb ulei cutie de viteze'],
        'differential' => ['Schimb ulei diferențial', 'Reparație punte'],
        '4x4_drivetrain' => ['Schimb ulei diferențial', 'Schimb cruce cardanică'],
        'transfer_case' => ['Schimb cruce cardanică'],
        'axles' => ['Reparație punte', 'Schimb rulment roată'],
        'suspension' => ['Schimb amortizoare', 'Schimb arcuri', 'Schimb bucșe punte'],
        'offroad_suspension' => ['Montaj kit de înălțare', 'Montaj suspensie completă 4x4'],
        'steering' => ['Schimb capete de bară și bielete', 'Reparație casetă de direcție'],
        'brakes' => ['Schimb plăcuțe frână față', 'Schimb plăcuțe și discuri frână', 'Schimb lichid de frână'],
        'electrical' => ['Diagnoză electrică', 'Test alternator și demaror'],
        'diagnostics' => ['Diagnoză computerizată', 'Citire și ștergere erori'],
        'adas_calibration' => ['Calibrare senzori după reparație'],
        'bodywork' => ['Reparație și vopsire element'],
        'painting' => ['Reparație și vopsire element'],
        'glass' => ['Schimb parbriz', 'Reparație parbriz'],
        'rustproofing' => ['Tratament anticoroziv'],
        'tyres' => ['Montaj și echilibrare anvelope', 'Vulcanizare'],
        'wheel_alignment' => ['Geometrie roți'],
        'wheel_balancing' => ['Montaj și echilibrare anvelope'],
        'air_conditioning' => ['Încărcare climatizare', 'Igienizare climatizare'],
        'exhaust' => ['Schimb tobă de eșapament'],
        'dpf' => ['Curățare filtru de particule'],
        'egr' => ['Curățare EGR și admisie'],
        'towing' => ['Tractare'],
        'detailing' => ['Polish și detailing'],
        'offroad_modifications' => ['Montaj bare metalice', 'Montaj scuturi metalice'],
        'winch_installation' => ['Montaj troliu', 'Montaj suport troliu'],
        'snorkel_installation' => ['Montaj snorkel'],
        'lighting_installation' => ['Montaj proiectoare', 'Montaj bară LED'],
        'towbar_installation' => ['Montaj cârlig de remorcare'],
    ];

    /** Below this, a service claim is too weak to print on a public page. */
    private const MIN_SERVICE_CONFIDENCE = 60;

    /** RAR spells some makes the way a form does rather than the way the catalogue names them. */
    private const MAKE_ALIASES = ['vw' => 'volkswagen', 'mercedes' => 'mercedes-benz', 'mb' => 'mercedes-benz'];

    /** Short words that are abbreviations or particles, not acronyms: title-cased like the rest. */
    private const SHORT_WORDS = ['STR', 'NR', 'BL', 'SC', 'AP', 'ET', 'SAT', 'COM', 'JUD', 'MUN', 'OR', 'DE', 'LA', 'SI', 'ȘI', 'DIN', 'PE', 'CU', 'AL', 'A'];

    /** @var list<int>|null */
    private ?array $publicSources = null;

    /** @var array<string, int>|null catalogue slug => id */
    private ?array $serviceIds = null;

    /** @var array<string, int>|null make slug => id */
    private ?array $makeIds = null;

    /** The workshops that belong in the directory. */
    public function eligible(): Builder
    {
        $sources = $this->publicSources() ?: [0];

        return Workshop::query()
            ->canonical()
            ->where('is_active', true)
            ->where('is_rar_authorized', true)
            ->whereHas('sourceLinks', fn (Builder $link) => $link->whereIn('data_source_id', $sources))
            ->whereHas('contacts', fn (Builder $contact) => $contact
                ->whereIn('type', ['phone', 'mobile'])
                ->whereIn('data_source_id', $sources));
    }

    /**
     * Lists every eligible workshop, refreshes the listings already made, and takes down the ones
     * whose workshop no longer qualifies.
     *
     * @return array{created: int, updated: int, relinked: int, unpublished: int}
     */
    public function run(?string $countyCode = null, ?int $limit = null, ?callable $progress = null): array
    {
        $totals = ['created' => 0, 'updated' => 0, 'relinked' => 0, 'unpublished' => 0];
        $done = 0;

        // Retired first: a duplicate's listing has to reach the workshop it was folded into
        // before that workshop is listed, or it would get a blank listing of its own and the old
        // one — its address on the web, its leads — would be taken down.
        //
        // A county or a limit means the run is not looking at the whole registry, so it cannot
        // tell which listings lost their workshop.
        if ($countyCode === null && $limit === null) {
            [$totals['relinked'], $totals['unpublished']] = $this->retire();
        }

        $this->eligible()
            ->when($countyCode !== null, fn (Builder $query) => $query->where('county_code', strtoupper((string) $countyCode)))
            ->with($this->relations())
            ->chunkById(200, function ($workshops) use (&$totals, &$done, $limit, $progress): bool {
                DB::transaction(function () use ($workshops, &$totals, &$done, $limit): void {
                    foreach ($workshops as $workshop) {
                        if ($limit !== null && $done >= $limit) {
                            return;
                        }

                        $totals[$this->sync($workshop)]++;
                        $done++;
                    }
                });

                if ($progress !== null) {
                    $progress($done);
                }

                return $limit === null || $done < $limit;
            });

        return $totals;
    }

    /**
     * Creates or refreshes the listing for one workshop.
     *
     * A new listing is published straight away but takes no appointment requests: until the
     * workshop has agreed to answer them, a request would reach us rather than the workshop.
     *
     * @return 'created'|'updated'
     */
    public function sync(Workshop $workshop): string
    {
        $workshop->loadMissing($this->relations());

        $shop = ServiceShop::withTrashed()->where('workshop_id', $workshop->id)->first();
        $created = $shop === null;
        $values = $this->values($workshop);

        if ($shop === null) {
            $shop = new ServiceShop([
                'workshop_id' => $workshop->id,
                'slug' => $this->freeSlug($values['name'], $values['city']),
                'status' => 'published',
                'accepts_appointments' => false,
            ]);
        }

        foreach ($values as $field => $value) {
            if ($created || $shop->followsRegistry($field)) {
                $shop->setAttribute($field, $value);
            }
        }

        $shop->registry_synced_at = now();
        $shop->save();

        if ($created || $shop->followsRegistry('makes')) {
            $shop->makes()->sync($this->makeIdsFor($workshop));
        }

        // Added, never removed: a job the registry vouches for may already carry a price someone
        // typed in, and a job it stops vouching for is for a person to take off.
        if ($created || $shop->followsRegistry('services')) {
            $missing = array_values(array_diff($this->serviceIdsFor($workshop), $shop->services()->pluck('services.id')->all()));

            if ($missing !== []) {
                $shop->services()->attach(array_fill_keys($missing, ['currency' => (string) config('emud.catalog.default_currency', 'RON')]));
            }
        }

        return $created ? 'created' : 'updated';
    }

    /**
     * The name a workshop is known by, out of the legal name RAR registers: without "SC" in front
     * or the legal form behind it, and out of the capitals registry text arrives in.
     */
    public static function displayName(string $legalName): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', str_replace(['"', '„', '”', '“', '\''], ' ', $legalName)));
        $name = (string) preg_replace('/^(?:s\.?\s?c\.?|p\.?\s?f\.?\s?a\.?)\s+/iu', '', $name);
        $name = (string) preg_replace('/[\s,.]+(?:s\.?\s?r\.?\s?l\.?(?:\s?-\s?d\.?)?|s\.?\s?a\.?|p\.?\s?f\.?\s?a\.?|i\.?\s?i\.?|i\.?\s?f\.?|s\.?\s?n\.?\s?c\.?|s\.?\s?c\.?\s?s\.?)$/iu', '', $name);
        $name = trim($name, " ,.-\t");

        return self::tidy($name) ?: trim($legalName);
    }

    /**
     * Registry text in capitals, written the way a person writes it. Text already in mixed case
     * is left alone, and short all-capital words that are acronyms (BMW, GPL) stay capitals.
     */
    public static function tidy(?string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if ($value === '' || mb_strtoupper($value) !== $value) {
            return $value;
        }

        return (string) preg_replace_callback('/\p{L}+/u', function (array $match): string {
            $word = $match[0];

            return mb_strlen($word) <= 3 && ! in_array($word, self::SHORT_WORDS, true)
                ? $word
                : mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }, $value);
    }

    /** @return array<int|string, mixed> */
    private function relations(): array
    {
        return [
            'contacts',
            'capabilities',
            'services.serviceType:id,key',
            'services.sourceRecord:id,data_source_id',
            'authorizations' => fn ($query) => $query->where('is_current', true),
        ];
    }

    /** @return array<string, mixed> the registry's value for every field it fills on the listing row */
    private function values(Workshop $workshop): array
    {
        // Only a point placed on the street. Most RAR coordinates are the town, or the company's
        // registered office, and a pin there sends a customer to the wrong door.
        $located = $workshop->geocode_status === 'located' && $workshop->hasCoordinates();
        $website = $this->contact($workshop, ['website'])?->value;
        $email = $this->contact($workshop, ['email'])?->value;

        return [
            'name' => mb_substr(self::displayName((string) $workshop->name), 0, 160),
            'county' => mb_substr(self::tidy($workshop->county) ?: 'Necunoscut', 0, 64),
            'city' => mb_substr(self::tidy($workshop->locality) ?: (self::tidy($workshop->county) ?: 'Necunoscut'), 0, 96),
            'address' => self::tidy($workshop->address) ?: null,
            'postal_code' => $workshop->postal_code ?: null,
            'latitude' => $located ? $workshop->latitude : null,
            'longitude' => $located ? $workshop->longitude : null,
            'phone' => mb_substr((string) $this->contact($workshop, ['phone', 'mobile'])?->value, 0, 32) ?: null,
            'email' => $email === null ? null : mb_substr($email, 0, 190),
            'website' => $website === null ? null : (preg_match('#^https?://#i', $website) ? $website : 'https://'.$website),
            'specialities' => $this->specialities($workshop),
            'certifications' => array_values(array_filter([
                $workshop->is_rar_authorized ? 'rar_authorised' : null,
                $workshop->is_itp ? 'rar_itp' : null,
            ])),
        ];
    }

    /**
     * What the directory's speciality filter offers, from what the registry has established.
     *
     * @return list<string>
     */
    private function specialities(Workshop $workshop): array
    {
        $offroad = (bool) $workshop->capabilities->firstWhere('capability', 'offroad')?->value;

        return array_values(array_filter([
            $workshop->supports_4x4 ? '4x4' : null,
            $offroad ? 'off-road' : null,
            $workshop->is_gpl_gnc ? 'GPL / GNC' : null,
            $workshop->is_tlv ? 'tahografe' : null,
            $workshop->supports_ev ? 'mașini electrice' : null,
            $workshop->supports_trucks ? 'camioane' : null,
        ]));
    }

    /**
     * The strongest contact of these kinds that a publishable source reported.
     *
     * @param  list<string>  $types
     */
    private function contact(Workshop $workshop, array $types): ?WorkshopContact
    {
        $sources = $this->publicSources();

        return $workshop->contacts
            ->filter(fn (WorkshopContact $contact): bool => in_array($contact->type, $types, true)
                && in_array((int) $contact->data_source_id, $sources, true))
            ->sortByDesc(fn (WorkshopContact $contact): array => [(int) $contact->is_primary, (int) $contact->confidence_score])
            ->first();
    }

    /** @return list<int> catalogue jobs the workshop's publishable, confident services vouch for */
    private function serviceIdsFor(Workshop $workshop): array
    {
        $catalogue = $this->serviceIds();
        $sources = $this->publicSources();
        $ids = [];

        foreach ($workshop->services as $service) {
            $evidence = $this->evidenceType($service);

            if ($evidence === 'inferred' || (int) $service->confidence_score < self::MIN_SERVICE_CONFIDENCE) {
                continue;
            }

            // Evidence counts when a publishable source gave it. A RAR service with no record of
            // its own was derived from the authorisations, which are the workshop's public source.
            $source = $service->sourceRecord?->data_source_id;

            if ($source === null ? $evidence !== 'rar_authorization' : ! in_array((int) $source, $sources, true)) {
                continue;
            }

            foreach (self::SERVICE_MAP[(string) $service->serviceType?->key] ?? [] as $job) {
                $slug = Str::slug($job);

                if (isset($catalogue[$slug])) {
                    $ids[] = $catalogue[$slug];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> makes the workshop's current, publishable RAR authorisations name */
    private function makeIdsFor(Workshop $workshop): array
    {
        $makes = $this->makeIds();
        $sources = $this->publicSources();
        $ids = [];

        foreach ($workshop->authorizations as $authorization) {
            if (! in_array((int) $authorization->data_source_id, $sources, true)) {
                continue;
            }

            foreach ((array) ($authorization->raw_data['brands'] ?? []) as $brand) {
                $slug = Str::slug((string) $brand);
                $slug = self::MAKE_ALIASES[$slug] ?? $slug;

                if (isset($makes[$slug])) {
                    $ids[] = $makes[$slug];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Listings whose workshop is no longer eligible: a merged duplicate hands its listing, and
     * its address on the web, to the workshop it was folded into when that one has none; any
     * other is taken down — unless a person published it by hand or someone is paying for it.
     *
     * @return array{0: int, 1: int} relinked, unpublished
     */
    private function retire(): array
    {
        $relinked = 0;
        $unpublished = 0;

        ServiceShop::query()
            ->whereNotNull('workshop_id')
            ->where('status', 'published')
            ->whereNotIn('workshop_id', $this->eligible()->select('workshops.id'))
            ->with('workshop')
            ->chunkById(200, function ($shops) use (&$relinked, &$unpublished): void {
                foreach ($shops as $shop) {
                    $survivor = $this->survivorOf($shop->workshop);

                    if ($survivor !== null && ! ServiceShop::withTrashed()->where('workshop_id', $survivor->id)->exists()) {
                        $shop->update(['workshop_id' => $survivor->id]);
                        $this->sync($survivor);
                        $relinked++;

                        continue;
                    }

                    if ($shop->followsRegistry('status') && ! $shop->isPromoted()) {
                        $shop->update(['status' => 'draft']);
                        $unpublished++;
                    }
                }
            });

        return [$relinked, $unpublished];
    }

    private function survivorOf(?Workshop $workshop): ?Workshop
    {
        $current = $workshop;

        // Bounded: a merge chain is one or two hops, and a cycle written by mistake must not hang
        // the run.
        for ($hops = 0; $current?->merged_into_id !== null && $hops < 5; $hops++) {
            $current = Workshop::query()->find($current->merged_into_id);
        }

        return $current !== null && $workshop !== null && $current->id !== $workshop->id ? $current : null;
    }

    /**
     * A slug no other listing has: the name, then the name and the town — two branches of one
     * company in one town share both, so a counter settles those.
     */
    private function freeSlug(string $name, string $city): string
    {
        $base = Str::limit(Str::slug($name) ?: 'service', 140, '');
        $withCity = Str::limit($base.'-'.Str::slug($city), 150, '');

        foreach ([$base, $withCity] as $candidate) {
            if (! $this->slugTaken($candidate)) {
                return $candidate;
            }
        }

        $counter = 2;

        while ($this->slugTaken($withCity.'-'.$counter)) {
            $counter++;
        }

        return $withCity.'-'.$counter;
    }

    private function slugTaken(string $slug): bool
    {
        return ServiceShop::withTrashed()->where('slug', $slug)->exists();
    }

    private function evidenceType(WorkshopService $service): string
    {
        $type = $service->evidence_type;

        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }

    /** @return list<int> */
    private function publicSources(): array
    {
        return $this->publicSources ??= WorkshopDataSource::query()
            ->where('is_public_output_allowed', true)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<string, int> */
    private function serviceIds(): array
    {
        return $this->serviceIds ??= Service::query()
            ->where('is_active', true)
            ->pluck('id', 'slug')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<string, int> */
    private function makeIds(): array
    {
        return $this->makeIds ??= VehicleMake::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (VehicleMake $make): array => [Str::slug((string) $make->name) => (int) $make->id])
            ->all();
    }
}
