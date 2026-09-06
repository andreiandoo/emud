<?php

namespace App\Catalog\Canonicalization\Vehicles;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Catalog\Canonicalization\SourceAssertionWriter;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;

class LifeOfCapoVehicleCanonicalizer implements CatalogRecordCanonicalizer
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly SourceAssertionWriter $assertions,
    ) {}

    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        if ($record->record_type === 'generic_part_taxonomy') {
            return CanonicalizationResult::skipped('Generic part taxonomy is retained as source data and is not a technical CatalogPart.');
        }

        $row = $record->raw_payload ?? [];
        $makeName = data_get($row, 'brand.name') ?? data_get($row, 'brand') ?? data_get($row, 'make');
        $modelName = data_get($row, 'model.name') ?? data_get($row, 'model');
        $generationName = data_get($row, 'generation.name') ?? data_get($row, 'generation') ?? $modelName;
        $yearFrom = (int) (data_get($row, 'yearFrom') ?? data_get($row, 'year_from') ?? 0);
        $yearTo = (int) (data_get($row, 'yearTo') ?? data_get($row, 'year_to') ?? 0);

        if (! is_string($makeName) || ! is_string($modelName) || ! $yearFrom) {
            return CanonicalizationResult::skipped('lifeofcapo vehicle record requires brand, model and yearFrom.');
        }

        $make = VehicleMake::query()->firstOrCreate(
            ['slug' => $this->normalizer->slug($makeName)],
            ['name' => trim($makeName), 'is_active' => true],
        );

        $model = VehicleModel::query()->firstOrCreate(
            ['make_id' => $make->id, 'slug' => $this->normalizer->slug($modelName)],
            ['name' => trim($modelName), 'is_active' => true],
        );

        $generation = VehicleGeneration::query()->firstOrCreate(
            ['model_id' => $model->id, 'name' => trim((string) $generationName)],
            [
                'year_from' => $yearFrom,
                'year_to' => $yearTo ?: null,
                'metadata' => ['origin' => 'lifeofcapo'],
            ],
        );

        $this->assertions->write($record, 'vehicle_generation', $generation->id, [
            'make' => $makeName,
            'model' => $modelName,
            'generation' => $generationName,
            'year_from' => $yearFrom,
            'year_to' => $yearTo ?: null,
        ], 70);

        return CanonicalizationResult::published('vehicle_generation', $generation->id, 70);
    }
}
