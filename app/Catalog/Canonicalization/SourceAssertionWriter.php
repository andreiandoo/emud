<?php

namespace App\Catalog\Canonicalization;

use App\Models\CatalogSourceAssertion;
use App\Models\CatalogSourceRecord;

class SourceAssertionWriter
{
    /** @param array<string, mixed> $values */
    public function write(CatalogSourceRecord $record, string $entityType, int $entityId, array $values, float $confidence = 100): void
    {
        $source = $record->source;

        foreach ($values as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            CatalogSourceAssertion::query()->updateOrCreate(
                [
                    'catalog_source_id' => $record->catalog_source_id,
                    'catalog_source_record_id' => $record->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'field_or_relation' => $field,
                ],
                [
                    'catalog_import_run_id' => $record->catalog_import_run_id,
                    'raw_value' => ['value' => $value],
                    'normalized_value' => ['value' => $value],
                    'confidence' => $confidence,
                    'status' => 'published',
                    'ecommerce_displayable' => (bool) $source->allow_ecommerce,
                    'api_redistributable' => (bool) $source->allow_api_redistribution,
                ],
            );
        }
    }
}
