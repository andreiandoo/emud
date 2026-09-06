<?php

namespace App\Catalog\Canonicalization\Vehicles;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Catalog\Canonicalization\SourceAssertionWriter;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogSourceRecord;
use App\Models\VehicleConfiguration;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Support\Arr;

class EeaVehicleCanonicalizer implements CatalogRecordCanonicalizer
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly SourceAssertionWriter $assertions,
    ) {}

    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult
    {
        $row = $record->raw_payload ?? [];
        $makeName = $this->first($row, ['Mk', 'make', 'Make', 'MAKE']);
        $modelName = $this->first($row, ['Cn', 'commercial_name', 'model', 'Model']);
        $year = $this->year($this->first($row, ['r', 'year', 'registration_year', 'year_of_registration']));

        if (! $makeName || ! $modelName || ! $year) {
            return CanonicalizationResult::skipped('EEA record requires make, commercial name/model and registration year.');
        }

        $typeApproval = $this->first($row, ['Tan', 'type_approval', 'typeApproval']);
        $type = $this->first($row, ['T', 'type']);
        $variant = $this->first($row, ['Va', 'variant']);
        $version = $this->first($row, ['Ve', 'version']);
        $fuel = $this->first($row, ['Ft', 'fuel', 'fuel_type']);
        $displacement = $this->integer($this->first($row, ['ec (cm3)', 'ec', 'engine_capacity', 'displacement_cc']));
        $powerKw = $this->decimal($this->first($row, ['ep (KW)', 'ep', 'power_kw']));

        $makeSlug = $this->normalizer->slug($makeName);
        $make = VehicleMake::query()->firstOrCreate(
            ['slug' => $makeSlug],
            ['name' => trim($makeName), 'is_active' => true],
        );

        $modelSlug = $this->normalizer->slug($modelName);
        $model = VehicleModel::query()->firstOrCreate(
            ['make_id' => $make->id, 'slug' => $modelSlug],
            ['name' => trim($modelName), 'is_active' => true],
        );

        $generationName = $type ? "Type {$type}" : trim($modelName);
        $generation = VehicleGeneration::query()->firstOrCreate(
            ['model_id' => $model->id, 'name' => $generationName],
            ['year_from' => $year, 'metadata' => ['derived_from' => 'EEA']],
        );

        if ($year < $generation->year_from) {
            $generation->update(['year_from' => $year]);
        }
        if (! $generation->year_to || $year > $generation->year_to) {
            $generation->update(['year_to' => $year]);
        }

        $engine = null;
        if ($displacement || $powerKw || $fuel) {
            $engineQuery = VehicleEngine::query()
                ->where('generation_id', $generation->id)
                ->where('displacement_cc', $displacement)
                ->where('power_kw', $powerKw)
                ->where('fuel_type', $fuel);

            $engine = $engineQuery->first();
            if (! $engine) {
                $engineName = trim(implode(' ', array_filter([
                    $displacement ? number_format($displacement / 1000, 1).'L' : null,
                    $fuel,
                    $powerKw ? rtrim(rtrim(number_format($powerKw, 2, '.', ''), '0'), '.').'kW' : null,
                ])));

                $engine = VehicleEngine::query()->create([
                    'generation_id' => $generation->id,
                    'name' => $engineName ?: 'EEA configuration',
                    'displacement_cc' => $displacement,
                    'displacement_l' => $displacement ? round($displacement / 1000, 2) : null,
                    'fuel_type' => $fuel,
                    'power_kw' => $powerKw,
                    'power_hp' => $powerKw ? (int) round($powerKw * 1.35962) : null,
                ]);
            }
        }

        $fingerprintPayload = [
            $make->id, $model->id, $typeApproval, $type, $variant, $version,
            $fuel, $displacement, $powerKw, $year,
        ];
        $fingerprint = hash('sha256', json_encode($fingerprintPayload, JSON_THROW_ON_ERROR));

        $configuration = VehicleConfiguration::query()->firstOrCreate(
            ['canonical_fingerprint' => $fingerprint],
            [
                'generation_id' => $generation->id,
                'engine_id' => $engine?->id,
                'year' => $year,
                'model_year_from' => $year,
                'model_year_to' => $year,
                'commercial_name' => trim($modelName),
                'drive_type' => 'unknown',
                'fuel_type' => $fuel,
                'displacement_cc' => $displacement,
                'power_kw' => $powerKw,
                'market' => 'EU',
                'eu_type_approval' => $typeApproval,
                'eu_type' => $type,
                'eu_variant' => $variant,
                'eu_version' => $version,
                'quality_score' => 92,
                'metadata' => ['origin' => 'EEA'],
            ],
        );

        $this->assertions->write($record, 'vehicle_configuration', $configuration->id, [
            'make' => $makeName,
            'model' => $modelName,
            'year' => $year,
            'eu_type_approval' => $typeApproval,
            'eu_type' => $type,
            'eu_variant' => $variant,
            'eu_version' => $version,
            'fuel_type' => $fuel,
            'displacement_cc' => $displacement,
            'power_kw' => $powerKw,
        ], 92);

        return CanonicalizationResult::published('vehicle_configuration', $configuration->id, 92);
    }

    private function first(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($row, $key);
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function year(?string $value): ?int
    {
        if (! $value) {
            return null;
        }
        if (preg_match('/(19|20)\d{2}/', $value, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function integer(?string $value): ?int
    {
        if (! $value) {
            return null;
        }
        $normalized = preg_replace('/[^0-9.-]/', '', str_replace(',', '.', $value));

        return is_numeric($normalized) ? (int) round((float) $normalized) : null;
    }

    private function decimal(?string $value): ?float
    {
        if (! $value) {
            return null;
        }
        $normalized = preg_replace('/[^0-9.-]/', '', str_replace(',', '.', $value));

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }
}
