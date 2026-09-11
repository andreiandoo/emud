<?php

namespace App\Livewire\Admin;

use App\Directory\ShopFacilities;
use App\Directory\WorkshopListingSync;
use App\Enums\ServicePromotionTier;
use App\Models\Service;
use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;
use App\Models\VehicleMake;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Everything a listing carries, on one page with tabs.
 *
 * The workshop is held by id rather than as a typed property: a route parameter named the same
 * as a typed model property makes Laravel resolve the record before mount() runs, which quietly
 * bypasses whatever scoping mount() was going to apply.
 */
#[Layout('layouts::admin')]
class ServiceShopEditor extends Component
{
    use WithFileUploads;

    /** @var array<string, string> */
    public const TABS = [
        'general' => 'General',
        'location' => 'Contact & locație',
        'hours' => 'Program',
        'services' => 'Servicii & prețuri',
        'gallery' => 'Galerie',
        'profile' => 'Mărci & dotări',
        'promotion' => 'Promovare',
    ];

    public ?int $shopId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $shopStatus = 'draft';

    public bool $acceptsAppointments = true;

    public bool $fitsPartsBoughtHere = false;

    public string $county = '';

    public string $city = '';

    public string $citySlug = '';

    public string $address = '';

    public string $postalCode = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $phone = '';

    public string $email = '';

    public string $website = '';

    public string $specialities = '';

    /** @var array<int, array{weekday: int, is_closed: bool, opens_at: string, closes_at: string}> */
    public array $hours = [];

    /** @var array<int, array{service_id: int|string, price_from: string, price_to: string, duration_minutes: string, note: string}> */
    public array $priceList = [];

    /** @var list<int> */
    public array $makeIds = [];

    /** @var list<string> */
    public array $amenities = [];

    /** @var list<string> */
    public array $paymentMethods = [];

    /** @var list<string> */
    public array $certifications = [];

    public string $promotionTier = 'none';

    public string $promotedUntil = '';

    public string $promotionNotes = '';

    public mixed $newImages = null;

    public string $saved = '';

    public function mount(?ServiceShop $shop = null): void
    {
        if ($shop?->exists) {
            $this->fill([
                'shopId' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'description' => (string) $shop->description,
                'shopStatus' => (string) $shop->status,
                'acceptsAppointments' => (bool) $shop->accepts_appointments,
                'fitsPartsBoughtHere' => (bool) $shop->fits_parts_bought_here,
                'county' => (string) $shop->county,
                'city' => (string) $shop->city,
                'citySlug' => (string) $shop->city_slug,
                'address' => (string) $shop->address,
                'postalCode' => (string) $shop->postal_code,
                'latitude' => (string) $shop->latitude,
                'longitude' => (string) $shop->longitude,
                'phone' => (string) $shop->phone,
                'email' => (string) $shop->email,
                'website' => (string) $shop->website,
                'specialities' => implode(', ', $shop->specialityList()),
                'makeIds' => $shop->makes()->pluck('vehicle_makes.id')->all(),
                'amenities' => array_keys($shop->amenityLabels()),
                'paymentMethods' => array_keys($shop->paymentLabels()),
                'certifications' => array_keys($shop->certificationLabels()),
                'promotionTier' => $shop->promotion_tier->value,
                'promotedUntil' => $shop->promoted_until?->toDateString() ?? '',
                'promotionNotes' => (string) $shop->promotion_notes,
            ]);

            $this->priceList = $shop->services->map(fn (Service $service) => [
                'service_id' => $service->id,
                'price_from' => (string) ($service->pivot->price_from ?? ''),
                'price_to' => (string) ($service->pivot->price_to ?? ''),
                'duration_minutes' => (string) ($service->pivot->duration_minutes ?? ''),
                'note' => (string) ($service->pivot->note ?? ''),
            ])->values()->all();
        }

        $this->hours = $this->hourRows($shop);
    }

    public function updatedName(string $value): void
    {
        if ($this->shopId === null || $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedCity(string $value): void
    {
        // Regenerated only while it has not been set by hand: the city slug is part of every
        // public URL for this workshop, and rewriting it on a typo would move the page.
        if ($this->citySlug === '' || $this->shopId === null) {
            $this->citySlug = Str::slug($value);
        }
    }

    public function addService(): void
    {
        $this->priceList[] = ['service_id' => '', 'price_from' => '', 'price_to' => '', 'duration_minutes' => '', 'note' => ''];
    }

    public function removeService(int $index): void
    {
        unset($this->priceList[$index]);
        $this->priceList = array_values($this->priceList);
    }

    public function removeImage(int $mediumId): void
    {
        $shop = $this->shop();

        abort_if($shop === null, 404);

        $shop->media()->whereKey($mediumId)->delete();
    }

    public function moveImage(int $mediumId, int $direction): void
    {
        $shop = $this->shop();

        abort_if($shop === null, 404);

        $media = $shop->media()->get();
        $index = $media->search(fn ($medium) => $medium->id === $mediumId);
        $target = $index + $direction;

        if ($index === false || $target < 0 || $target >= $media->count()) {
            return;
        }

        // Positions are rewritten for the whole set rather than swapped in place: imports and
        // deletions leave gaps and duplicates, and a swap between two equal positions does
        // nothing at all.
        $ordered = $media->values();
        $moved = $ordered->splice($index, 1)->first();
        $ordered->splice($target, 0, [$moved]);

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $position => $medium) {
                $medium->update(['position' => $position]);
            }
        });
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/', Rule::unique('service_shops', 'slug')->ignore($this->shopId)],
            'description' => ['nullable', 'string'],
            'shopStatus' => ['required', 'in:draft,published'],
            'county' => ['required', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:96'],
            'citySlug' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9-]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:16'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:255'],
            'specialities' => ['nullable', 'string', 'max:500'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'priceList.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'priceList.*.price_from' => ['nullable', 'numeric', 'min:0'],
            'priceList.*.price_to' => ['nullable', 'numeric', 'min:0'],
            'priceList.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:2880'],
            'priceList.*.note' => ['nullable', 'string', 'max:160'],
            'makeIds.*' => ['integer', 'exists:vehicle_makes,id'],
            'amenities.*' => [Rule::in(array_keys(ShopFacilities::AMENITIES))],
            'paymentMethods.*' => [Rule::in(array_keys(ShopFacilities::PAYMENT_METHODS))],
            'certifications.*' => [Rule::in(array_keys(ShopFacilities::CERTIFICATIONS))],
            'promotionTier' => ['required', Rule::in(array_column(ServicePromotionTier::cases(), 'value'))],
            // A paid tier with no end date would run forever without anyone revisiting it, which
            // is how a listing keeps a position nobody is still paying for.
            'promotedUntil' => [Rule::requiredIf($this->promotionTier !== 'none'), 'nullable', 'date'],
            'promotionNotes' => ['nullable', 'string', 'max:500'],
            'newImages.*' => ['nullable', 'image', 'max:4096'],
        ]);

        $shop = DB::transaction(function () use ($data): ServiceShop {
            $shop = $this->shop() ?? new ServiceShop;

            // A listing built from the registry: whatever the registry fills and this save
            // changes is taken over, and the next sync leaves it as written here.
            if ($shop->exists && $shop->workshop_id !== null) {
                $shop->registry_locked = $this->takenOver($shop, $data);
            }

            // Columns listed one by one rather than spread from the validated array: the form's
            // property names are not the table's column names, and a spread would try to write
            // "shopStatus" to a column that does not exist.
            $shop->fill([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?: null,
                'status' => $data['shopStatus'],
                'accepts_appointments' => $this->acceptsAppointments,
                'fits_parts_bought_here' => $this->fitsPartsBoughtHere,
                'county' => $data['county'],
                'city' => $data['city'],
                'city_slug' => $data['citySlug'],
                'address' => $data['address'] ?: null,
                'postal_code' => $data['postalCode'] ?: null,
                'latitude' => $data['latitude'] === '' ? null : $data['latitude'],
                'longitude' => $data['longitude'] === '' ? null : $data['longitude'],
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'website' => $data['website'] ?: null,
                'specialities' => $this->specialityList($data['specialities'] ?? ''),
                'amenities' => array_values($this->amenities),
                'payment_methods' => array_values($this->paymentMethods),
                'certifications' => array_values($this->certifications),
                'promotion_tier' => $data['promotionTier'],
                // Dropping the tier clears the date with it: an end date left behind on a free
                // listing reads as a promotion that is still running.
                'promoted_until' => $data['promotionTier'] === 'none' ? null : ($data['promotedUntil'] ?: null),
                'promotion_notes' => $data['promotionNotes'] ?: null,
            ])->save();

            $this->syncHours($shop);
            $this->syncServices($shop);
            $shop->makes()->sync($this->makeIds);

            return $shop;
        });

        $this->storeImages($shop);

        $this->shopId = $shop->id;
        $this->newImages = null;
        $this->saved = 'Service-ul a fost salvat.';
    }

    /** Pulls the registry's current values into every field that still follows it. */
    public function syncFromRegistry(WorkshopListingSync $sync): void
    {
        $shop = $this->shop();

        abort_if($shop?->workshop === null, 404);

        $sync->sync($shop->workshop);
        $this->reload();
        $this->saved = 'Fișa a fost actualizată din registru.';
    }

    /** Hands every taken-over field back to the registry, and syncs. */
    public function followRegistryAgain(WorkshopListingSync $sync): void
    {
        $shop = $this->shop();

        abort_if($shop?->workshop === null, 404);

        $shop->update(['registry_locked' => null]);
        $sync->sync($shop->workshop);
        $this->reload();
        $this->saved = 'Toate câmpurile urmează din nou registrul.';
    }

    public function render()
    {
        $shop = $this->shop();

        return view('livewire.admin.service-shop-editor', [
            'tabs' => self::TABS,
            'shop' => $shop,
            'lockedFields' => $shop?->lockedFields() ?? [],
            'fieldLabels' => WorkshopListingSync::FIELDS,
            'services' => Service::query()->with('serviceCategory')->orderBy('name')->get(),
            'makes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tiers' => ServicePromotionTier::cases(),
            'amenityOptions' => ShopFacilities::AMENITIES,
            'paymentOptions' => ShopFacilities::PAYMENT_METHODS,
            'certificationOptions' => ShopFacilities::CERTIFICATIONS,
            'leadTotals' => $this->leadTotals(),
        ]);
    }

    /**
     * Lead counts in two grouped queries rather than one per type per period, which is what a
     * loop in the template would have cost.
     *
     * @return array{month: array<string, int>, all: array<string, int>}
     */
    private function leadTotals(): array
    {
        if ($this->shopId === null) {
            return ['month' => [], 'all' => []];
        }

        $rows = ServiceShopLeadEvent::query()
            // A CASE sum rather than COUNT ... FILTER: the aggregate filter clause is not
            // portable, and this query runs on both engines the suite uses.
            ->selectRaw('type, count(*) as total, sum(case when created_at >= ? then 1 else 0 end) as this_month', [now()->startOfMonth()])
            ->where('service_shop_id', $this->shopId)
            ->groupBy('type')
            ->get();

        return [
            'month' => $rows->mapWithKeys(fn ($row) => [$row->type->value => (int) $row->this_month])->all(),
            'all' => $rows->mapWithKeys(fn ($row) => [$row->type->value => (int) $row->total])->all(),
        ];
    }

    private function shop(): ?ServiceShop
    {
        return $this->shopId === null
            ? null
            : ServiceShop::query()->with(['media', 'services'])->find($this->shopId);
    }

    /** @return list<string> */
    private function specialityList(string $raw): array
    {
        return collect(explode(',', $raw))->map(fn (string $item) => trim($item))->filter()->unique()->values()->all();
    }

    /**
     * The registry-filled fields this save changes, added to the ones already taken over.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function takenOver(ServiceShop $shop, array $data): array
    {
        $before = [
            'name' => (string) $shop->name,
            'county' => (string) $shop->county,
            'city' => (string) $shop->city,
            'address' => (string) $shop->address,
            'postal_code' => (string) $shop->postal_code,
            'latitude' => (string) $shop->latitude,
            'longitude' => (string) $shop->longitude,
            'phone' => (string) $shop->phone,
            'email' => (string) $shop->email,
            'website' => (string) $shop->website,
            'specialities' => $shop->specialityList(),
            'certifications' => $this->keys(array_keys($shop->certificationLabels())),
            'makes' => $this->ids($shop->makes()->pluck('vehicle_makes.id')->all()),
            'services' => $this->ids($shop->services()->pluck('services.id')->all()),
            'status' => (string) $shop->status,
        ];

        $after = [
            'name' => (string) $data['name'],
            'county' => (string) $data['county'],
            'city' => (string) $data['city'],
            'address' => (string) ($data['address'] ?? ''),
            'postal_code' => (string) ($data['postalCode'] ?? ''),
            'latitude' => (string) ($data['latitude'] ?? ''),
            'longitude' => (string) ($data['longitude'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'website' => (string) ($data['website'] ?? ''),
            'specialities' => $this->specialityList((string) ($data['specialities'] ?? '')),
            'certifications' => $this->keys($this->certifications),
            'makes' => $this->ids($this->makeIds),
            'services' => $this->ids(array_column($this->priceList, 'service_id')),
            'status' => (string) $data['shopStatus'],
        ];

        $changed = array_keys(array_filter(
            $after,
            fn (mixed $value, string $field): bool => $value !== $before[$field],
            ARRAY_FILTER_USE_BOTH,
        ));

        return array_values(array_unique([...$shop->lockedFields(), ...$changed]));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $values))));
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function keys(array $values): array
    {
        $keys = array_values(array_unique(array_map('strval', $values)));
        sort($keys);

        return $keys;
    }

    private function reload(): void
    {
        $this->mount(ServiceShop::query()->findOrFail($this->shopId));
    }

    /** @return array<int, array{weekday: int, is_closed: bool, opens_at: string, closes_at: string}> */
    private function hourRows(?ServiceShop $shop): array
    {
        $existing = $shop?->exists ? $shop->hours->keyBy('weekday') : collect();

        return collect(range(1, 7))->map(function (int $weekday) use ($existing): array {
            $row = $existing->get($weekday);

            return [
                'weekday' => $weekday,
                // A workshop with no row yet is closed until someone says otherwise, which is
                // safer than advertising hours nobody entered.
                'is_closed' => $row === null ? true : (bool) $row->is_closed,
                'opens_at' => $row?->opens_at ? substr((string) $row->opens_at, 0, 5) : '',
                'closes_at' => $row?->closes_at ? substr((string) $row->closes_at, 0, 5) : '',
            ];
        })->all();
    }

    private function syncHours(ServiceShop $shop): void
    {
        foreach ($this->hours as $row) {
            $closed = (bool) ($row['is_closed'] ?? false) || ($row['opens_at'] ?? '') === '' || ($row['closes_at'] ?? '') === '';

            $shop->hours()->updateOrCreate(
                ['weekday' => (int) $row['weekday']],
                [
                    'is_closed' => $closed,
                    // A closed day stores no hours, so nothing downstream has to decide which of
                    // two answers to believe.
                    'opens_at' => $closed ? null : $row['opens_at'],
                    'closes_at' => $closed ? null : $row['closes_at'],
                ],
            );
        }
    }

    private function syncServices(ServiceShop $shop): void
    {
        $payload = [];

        foreach ($this->priceList as $row) {
            $serviceId = (int) $row['service_id'];

            if ($serviceId === 0) {
                continue;
            }

            $payload[$serviceId] = [
                'price_from' => $row['price_from'] === '' ? null : $row['price_from'],
                'price_to' => $row['price_to'] === '' ? null : $row['price_to'],
                'currency' => (string) config('emud.catalog.default_currency', 'RON'),
                'duration_minutes' => $row['duration_minutes'] === '' ? null : (int) $row['duration_minutes'],
                'note' => $row['note'] ?: null,
            ];
        }

        $shop->services()->sync($payload);
    }

    private function storeImages(ServiceShop $shop): void
    {
        foreach ((array) $this->newImages as $image) {
            if ($image === null) {
                continue;
            }

            $shop->media()->create([
                'disk' => 'public',
                'path' => $image->store('service-shops', 'public'),
                'position' => (int) $shop->media()->max('position') + 1,
            ]);
        }
    }
}
