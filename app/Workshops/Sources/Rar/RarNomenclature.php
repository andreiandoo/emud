<?php

namespace App\Workshops\Sources\Rar;

use App\Models\WorkshopDataSource;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Support\TextNormalizer;

/**
 * RAR's own wording for its codes: "A1.2.1.3. în trepte, asistată cu gestiune electronică şi cu
 * tracţiune permanentă pe mai multe axe".
 *
 * Read from the copy of the portal's translation file stored as a source record (refreshed by
 * workshops:rar:discover), and from the bundled copy when none has been stored yet. The wording
 * is kept exactly as published, prefix and all; nothing here maps it to our own taxonomy.
 */
class RarNomenclature
{
    public const RECORD_TYPE = 'nomenclature';

    public const EXTERNAL_ID = 'portal-ro';

    /** @param array|null $document a translation file to read instead of the stored copy; [] means the bundled one */
    public function __construct(private ?array $document = null) {}

    public function activity(string $section, string $code): ?string
    {
        $live = $this->document()['RarAuthorizationActivitiesEnumBySection'][$section][$code] ?? null;

        return TextNormalizer::clean($live ?? RarBundledNomenclature::ACTIVITIES[$section][$code] ?? null);
    }

    public function itpClass(string $code): ?string
    {
        return TextNormalizer::clean($this->document()['RarItpClassEnum'][$code] ?? RarBundledNomenclature::ITP_CLASSES[$code] ?? null);
    }

    /** "Înălțime maximă autovehicul Hmax=@param" with the station's own value put in. */
    public function itpLimitation(string $code, mixed $value): string
    {
        $template = $this->document()['RarItpLimitationsEnum'][$code] ?? RarBundledNomenclature::ITP_LIMITATIONS[$code] ?? $code;
        $value = TextNormalizer::clean(is_scalar($value) ? (string) $value : null);

        if (str_contains($template, '@param')) {
            return (string) TextNormalizer::clean(str_replace('@param', $value ?? '?', $template));
        }

        return (string) TextNormalizer::clean($value === null ? $template : "{$template} {$value}");
    }

    public function itpInterdiction(string $code): string
    {
        return (string) TextNormalizer::clean($this->document()['RarItpInterdictionsEnum'][$code] ?? RarBundledNomenclature::ITP_INTERDICTIONS[$code] ?? $code);
    }

    public function itpObservation(string $code): string
    {
        return (string) TextNormalizer::clean($this->document()['RarItpObservationsEnum'][$code] ?? RarBundledNomenclature::ITP_OBSERVATIONS[$code] ?? $code);
    }

    public function b4Component(string $code): ?string
    {
        return TextNormalizer::clean($this->document()['rar']['b4ActivitiesComponents'][$code] ?? RarBundledNomenclature::B4_COMPONENTS[$code] ?? null);
    }

    public function vehicleCategory(string $code): string
    {
        return (string) ($this->document()['RarAuthorizationHomologationCategoriesEnum'][$code] ?? RarBundledNomenclature::VEHICLE_CATEGORIES[$code] ?? $code);
    }

    /** "live" once a copy has been stored, "bundled" before that. */
    public function origin(): string
    {
        return $this->document() === [] ? 'bundled' : 'live';
    }

    /** Stores a freshly fetched translation file as the copy every lookup reads from. */
    public function store(array $document, SourceRecordStore $store): WorkshopSourceRecord
    {
        $result = $store->store(
            WorkshopDataSource::forKey(DataSourceCatalog::rarKey('SERVICE')),
            new SourceRecordData(
                recordType: self::RECORD_TYPE,
                externalId: self::EXTERNAL_ID,
                payload: $document,
                sourceReference: (string) config('workshops.rar.nomenclature_url'),
                httpStatus: 200,
            ),
        );
        $result->record->markParsed();
        $this->document = null;

        return $result->record;
    }

    private function document(): array
    {
        if ($this->document === null) {
            $record = WorkshopSourceRecord::query()
                ->where('record_type', self::RECORD_TYPE)
                ->where('external_id', self::EXTERNAL_ID)
                ->whereHas('dataSource', fn ($query) => $query->where('key', DataSourceCatalog::rarKey('SERVICE')))
                ->first();

            $this->document = is_array($record?->payload) ? $record->payload : [];
        }

        return $this->document;
    }
}
