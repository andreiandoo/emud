<?php

namespace App\Workshops\Export;

use App\Models\Workshop;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Search\WorkshopFilters;
use App\Workshops\Search\WorkshopSearch;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Writes the registry, or a filtered part of it, to CSV or JSON, streaming a chunk at a time.
 *
 * The CSV is UTF-8 with a byte-order mark and comma separated, which Excel and LibreOffice open
 * with diacritics intact. With $publicOnly, data from sources not cleared for publication is left
 * out, and so is every workshop that only such a source knows.
 */
class WorkshopExporter
{
    public const COLUMNS = [
        'id', 'nume', 'firma', 'cui', 'nr_reg_com', 'stare_firma', 'judet', 'cod_judet', 'localitate', 'adresa', 'cod_postal',
        'latitudine', 'longitudine', 'sursa_coordonate', 'incredere_coordonate', 'telefoane', 'emailuri', 'website', 'facebook',
        'instagram', 'whatsapp', 'autorizat_rar_service', 'itp', 'gpl_gnc', 'tahografe', 'modificari_b4', 'autorizatii_rar',
        'coduri_activitati_rar', 'servicii_autorizate_rar', 'servicii_declarate', 'suporta_4x4', 'tractiune_integrala_permanenta',
        'suporta_ev', 'suporta_hibrid', 'suporta_camioane', 'scor_offroad', 'scor_incredere', 'surse', 'vazut_prima_data',
        'vazut_ultima_data', 'verificat_la',
    ];

    public function __construct(private WorkshopSearch $search) {}

    public function export(WorkshopFilters $filters, string $format, string $path, bool $publicOnly = false): int
    {
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Cannot write {$path}");
        }

        $allowed = $publicOnly ? WorkshopDataSource::query()->where('is_public_output_allowed', true)->pluck('id')->all() : null;
        $query = $this->search->query($filters)
            ->with([
                'company',
                'contacts',
                'authorizations' => fn ($q) => $q->where('is_current', true)->with('activities:id,workshop_authorization_id,code,display_code'),
                'services.serviceType:id,key',
                'capabilities',
                'sourceLinks.dataSource:id,key,is_public_output_allowed',
            ])
            ->when($allowed !== null, fn (Builder $q) => $q->whereHas('sourceLinks', fn (Builder $link) => $link->whereIn('data_source_id', $allowed ?: [0])));

        $rows = 0;
        $json = $format === 'json';

        if ($json) {
            fwrite($handle, "[\n");
        } else {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::COLUMNS);
        }

        // chunkById keeps memory flat whatever the filter; the query's own order is replaced.
        $query->reorder()->chunkById(500, function ($workshops) use ($handle, $json, $allowed, &$rows): void {
            foreach ($workshops as $workshop) {
                $row = $this->row($workshop, $allowed);

                if ($json) {
                    fwrite($handle, ($rows > 0 ? ",\n" : '').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } else {
                    fputcsv($handle, array_map(fn (mixed $value): string => is_array($value) ? implode(' | ', $value) : (is_bool($value) ? ($value ? 'da' : 'nu') : (string) $value), $row));
                }

                $rows++;
            }
        });

        if ($json) {
            fwrite($handle, "\n]\n");
        }

        fclose($handle);

        return $rows;
    }

    /** @param list<int>|null $allowedSources */
    public function row(Workshop $workshop, ?array $allowedSources = null): array
    {
        $contacts = $workshop->contacts
            ->filter(fn (WorkshopContact $contact): bool => $allowedSources === null || in_array($contact->data_source_id, $allowedSources, true))
            ->sortByDesc(fn (WorkshopContact $contact): array => [$contact->is_primary, $contact->confidence_score]);
        $values = fn (array $types): array => $contacts->whereIn('type', $types)->pluck('value')->unique()->values()->all();
        $services = $workshop->services;
        $capability = fn (string $key): ?bool => $workshop->capabilities->firstWhere('capability', $key)?->value;

        return [
            'id' => $workshop->id,
            'nume' => $workshop->name,
            'firma' => $workshop->company?->legal_name,
            'cui' => $workshop->company?->cui,
            'nr_reg_com' => $workshop->company?->registration_number,
            'stare_firma' => $workshop->company?->status,
            'judet' => $workshop->county,
            'cod_judet' => $workshop->county_code,
            'localitate' => $workshop->locality,
            'adresa' => $workshop->address,
            'cod_postal' => $workshop->postal_code,
            'latitudine' => $workshop->latitude,
            'longitudine' => $workshop->longitude,
            'sursa_coordonate' => $workshop->coordinates_source,
            'incredere_coordonate' => $workshop->coordinates_confidence,
            'telefoane' => $values(['phone', 'mobile']),
            'emailuri' => $values(['email']),
            'website' => $values(['website'])[0] ?? null,
            'facebook' => $values(['facebook'])[0] ?? null,
            'instagram' => $values(['instagram'])[0] ?? null,
            'whatsapp' => $values(['whatsapp'])[0] ?? null,
            'autorizat_rar_service' => (bool) $workshop->is_rar_authorized,
            'itp' => (bool) $workshop->is_itp,
            'gpl_gnc' => (bool) $workshop->is_gpl_gnc,
            'tahografe' => (bool) $workshop->is_tlv,
            'modificari_b4' => (bool) $workshop->is_modification_authorized,
            'autorizatii_rar' => $workshop->authorizations->map(fn ($a): string => $a->system.' '.($a->authorization_number ?? '?').($a->valid_until ? ' până la '.$a->valid_until->format('d.m.Y') : ''))->values()->all(),
            'coduri_activitati_rar' => $workshop->authorizations->flatMap(fn ($a) => $a->activities->pluck('display_code'))->unique()->sort()->values()->all(),
            'servicii_autorizate_rar' => $services->filter(fn ($s): bool => $s->evidence_type->value === 'rar_authorization')->map(fn ($s): string => ServiceTaxonomy::name((string) $s->serviceType?->key))->unique()->values()->all(),
            'servicii_declarate' => $services->filter(fn ($s): bool => in_array($s->evidence_type->value, ['website', 'osm', 'manual'], true))->map(fn ($s): string => ServiceTaxonomy::name((string) $s->serviceType?->key))->unique()->values()->all(),
            'suporta_4x4' => $workshop->supports_4x4,
            'tractiune_integrala_permanenta' => $capability('awd_permanent'),
            'suporta_ev' => $workshop->supports_ev,
            'suporta_hibrid' => $workshop->supports_hybrid,
            'suporta_camioane' => $workshop->supports_trucks,
            'scor_offroad' => $workshop->offroad_score,
            'scor_incredere' => $workshop->confidence_score,
            'surse' => $workshop->sourceLinks->map(fn ($link): ?string => $link->dataSource?->key)->filter()->unique()->values()->all(),
            'vazut_prima_data' => $workshop->first_seen_at?->toDateString(),
            'vazut_ultima_data' => $workshop->last_seen_at?->toDateString(),
            'verificat_la' => $workshop->last_verified_at?->toDateString(),
        ];
    }
}
