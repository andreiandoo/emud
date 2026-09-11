<?php

namespace App\Catalog\Vehicles\Vin;

use App\Catalog\Api\CatalogPublicationScope;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Collection;

class VpicDecodedVehicleMatcher
{
    public function __construct(private readonly CatalogPublicationScope $publicationScope) {}

    public function match(array $decoded, bool $publicContext = false, ?string $vin = null): VinResolutionResult
    {
        $make = trim((string) $this->firstValue($decoded['Make'] ?? null));
        $model = trim((string) $this->firstValue($decoded['Model'] ?? null));
        $year = (int) $this->firstValue($decoded['ModelYear'] ?? null);
        $publicDecoded = $this->publicDecoded($decoded);

        // The storefront only; the public API keeps answering from vPIC alone. vPIC knows little
        // about cars built for Europe, and where the VIN spells out the model line itself, that
        // fills the gap.
        $models = match (true) {
            $model !== '' => [$model],
            $publicContext => [],
            default => VinModelHints::modelNames($vin ?? (string) $this->firstValue($decoded['VIN'] ?? '')),
        };

        if ($make === '' || $models === [] || $year === 0) {
            return new VinResolutionResult(
                'basic_only',
                null,
                45,
                decoded: $publicDecoded,
                missing: ['make/model/year'],
                makeId: $this->catalogMakeId($make, $publicContext),
            );
        }

        $matches = $this->configurations($make, $models, $year, $publicContext);

        // A model year runs ahead of the calendar: a car built in the autumn is sold as the next
        // year's, so a configuration filed under the build year is the next best match.
        if ($matches->isEmpty() && ! $publicContext) {
            $matches = $this->configurations($make, $models, $year - 1, $publicContext);
        }

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

        // The catalogue has the model but no build for this year: the model is still worth
        // selecting, which narrows the parts far more than nothing does.
        $catalogModel = $publicContext ? null : $this->catalogModel($make, $models);

        if ($catalogModel !== null) {
            return new VinResolutionResult(
                'model_only',
                null,
                50,
                decoded: $publicDecoded,
                missing: ['engine/configuration discriminator'],
                makeId: (int) $catalogModel->make_id,
                modelId: (int) $catalogModel->id,
                generationId: $this->generationId((int) $catalogModel->id, $year),
            );
        }

        return new VinResolutionResult(
            'basic_only',
            null,
            55,
            decoded: $publicDecoded,
            missing: ['canonical vehicle mapping'],
            makeId: $this->catalogMakeId($make, $publicContext),
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

    /**
     * @param  list<string>  $models
     * @return Collection<int, VehicleConfiguration>
     */
    private function configurations(string $make, array $models, int $year, bool $publicContext): Collection
    {
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
            ->whereHas('generation.model.make', fn ($query) => $query->whereRaw('LOWER(name) = LOWER(?)', [$make]))
            ->whereHas('generation.model', fn ($query) => $query->where(function ($query) use ($models): void {
                foreach ($models as $name) {
                    $query->orWhereRaw('LOWER(name) = LOWER(?)', [$name]);
                }
            }));

        if ($publicContext) {
            $this->publicationScope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');
        }

        return $query->limit(20)->get();
    }

    /** @param  list<string>  $models  the names to try, most specific first */
    private function catalogModel(string $make, array $models): ?VehicleModel
    {
        foreach ($models as $name) {
            $found = VehicleModel::query()
                ->whereRaw('LOWER(name) = LOWER(?)', [$name])
                ->whereHas('make', fn ($query) => $query->whereRaw('LOWER(name) = LOWER(?)', [$make]))
                ->first();

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** The generation the model year falls in, when it falls in exactly one; otherwise the customer's to choose. */
    private function generationId(int $modelId, int $year): ?int
    {
        $ids = VehicleGeneration::query()
            ->where('model_id', $modelId)
            ->where(fn ($query) => $query->whereNull('year_from')->orWhere('year_from', '<=', $year))
            ->where(fn ($query) => $query->whereNull('year_to')->orWhere('year_to', '>=', $year - 1))
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function catalogMakeId(string $make, bool $publicContext): ?int
    {
        if ($make === '' || $publicContext) {
            return null;
        }

        $id = VehicleMake::query()->whereRaw('LOWER(name) = LOWER(?)', [$make])->value('id');

        return $id === null ? null : (int) $id;
    }

    private function firstValue(mixed $value): mixed
    {
        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}
