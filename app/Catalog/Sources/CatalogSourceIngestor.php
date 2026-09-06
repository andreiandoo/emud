<?php

namespace App\Catalog\Sources;

use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use Illuminate\Support\Arr;

class CatalogSourceIngestor
{
    /** @param array<string, mixed> $row */
    public function store(CatalogSource $source, CatalogImportRun $run, array $row): CatalogSourceRecord
    {
        $mapping = $source->field_mapping ?? [];
        $externalId = (string) data_get($row, $mapping['external_id'] ?? 'id', '');
        $recordType = (string) data_get($row, $mapping['record_type'] ?? 'record_type', $run->mode);

        if ($externalId === '') {
            $externalId = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }

        $checksum = hash('sha256', json_encode(Arr::sortRecursive($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $record = CatalogSourceRecord::query()->firstOrNew([
            'catalog_source_id' => $source->id,
            'record_type' => $recordType,
            'external_id' => $externalId,
        ]);

        $record->fill([
            'catalog_source_release_id' => $run->catalog_source_release_id,
            'catalog_import_run_id' => $run->id,
            'checksum_sha256' => $checksum,
            'raw_payload' => $row,
            'mapping_status' => $record->exists && $record->checksum_sha256 === $checksum ? $record->mapping_status : 'unprocessed',
            'deleted_at_source' => false,
            'last_seen_at' => now(),
        ]);
        $record->save();

        return $record;
    }
}
