<?php

namespace App\Catalog\Vehicles\Vin;

use App\Catalog\Api\CatalogPublicationScope;
use App\Models\VehicleConfiguration;

class VpicDecodedVehicleMatcher
{
    public function __construct(private readonly CatalogPublicationScope $publicationScope) {}

    public function match(array $decoded, bool $publicContext = false): VinResolutionResult
    {
        $make = trim((string) $this->firstValue($decoded['Make'] ?? null));
        $model = trim((string) $this->firstValue($decoded['Model'] ?? null));
        $year = (int) $this->firstValue($decoded['ModelYear'] ?? null);
        $publicDecoded = $this->publicDecoded($decoded);

        if ($make === '' || $model === '' || $year === 0) {
            return new VinResolutionResult(
                'basic_only',
                null,
                45,
                decoded: $publicDecoded,
                missing: ['make/model/year'],
            );
        }

        $query = VehicleConfiguration::query()
            ->with(['generation.model.make', 'engine'])
            ->where(function ($query) use ($year): void {
                $query->where('year', $year)
                    ->orWhere(function ($query) use ($year): void {
                        $query->whereNull('year')
                            ->where('model_year_from', '<=', $year)
                            ->where('model_year_to', '>=', $year);
                    });
            })
            ->whereHas('generation.model.make', fn ($query) => $query->where('name', 'ilike', $make))
            ->whereHas('generation.model', fn ($query) => $query->where('name', 'ilike', $model));

        if ($publicContext) {
            $this->publicationScope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');
        }

        $matches = $query->limit(20)->get();

        if ($matches->count() === 1) {
            return new VinResolutionResult(
                'high_confidence',
                $matches->first()->id,
                88,
                decoded: $publicDecoded,
            );
        }

        if ($matches->isNotEmpty()) {
            return new VinResolutionResult(
                'ambiguous',
                null,
                65,
                decoded: $publicDecoded,
                candidates: $matches->map(fn ($vehicle) => [
                    'id' => $vehicle->id,
                    'make' => $vehicle->generation?->model?->make?->name,
                    'model' => $vehicle->generation?->model?->name,
                    'generation' => $vehicle->generation?->name,
                    'engine' => $vehicle->engine?->name,
                    'engine_code' => $vehicle->engine?->engine_code,
                    'year' => $vehicle->year,
                    'model_year_from' => $vehicle->model_year_from,
                    'model_year_to' => $vehicle->model_year_to,
                ])->values()->all(),
                missing: ['engine/configuration discriminator'],
            );
        }

        return new VinResolutionResult(
            'basic_only',
            null,
            55,
            decoded: $publicDecoded,
            missing: ['canonical vehicle mapping'],
        );
    }

    public function publicDecoded(array $decoded): array
    {
        $keys = [
            'Make', 'Model', 'ModelYear', 'Manufacturer', 'VehicleType', 'BodyClass',
            'EngineModel', 'DisplacementL', 'FuelTypePrimary', 'EngineHP', 'DriveType',
            'TransmissionStyle', 'PlantCountry', 'Series', 'Trim', 'ErrorCode', 'ErrorText',
        ];

        return collect($keys)
            ->mapWithKeys(fn ($key) => [$key => $decoded[$key] ?? null])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function firstValue(mixed $value): mixed
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}
