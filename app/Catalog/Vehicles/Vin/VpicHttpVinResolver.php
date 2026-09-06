<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;
use App\Models\VehicleConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VpicHttpVinResolver
{
    public function resolve(string $vin, bool $publicContext = false): VinResolutionResult
    {
        $vin = strtoupper(trim($vin));
        if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
            return new VinResolutionResult('invalid', null, 0, message: 'VIN must contain 17 valid VIN characters.');
        }

        $source = CatalogSource::query()->where('code', 'VPIC')->where('is_active', true)->first();
        if (! $source) {
            return new VinResolutionResult('unsupported', null, 0, message: 'vPIC source is not enabled.');
        }
        if ($publicContext && ! $source->allow_api_redistribution) {
            return new VinResolutionResult('unsupported', null, 0, message: 'The enabled VIN source is not approved for public API redistribution.');
        }

        $decoded = Cache::remember('catalog:vin:vpic:'.hash('sha256', $vin), now()->addDay(), function () use ($source, $vin): array {
            $base = rtrim((string) ($source->settings['api_base_url'] ?? $source->base_url ?? 'https://vpic.nhtsa.dot.gov'), '/');
            $url = $base.'/api/vehicles/DecodeVinValues/'.rawurlencode($vin).'?format=json';
            $response = Http::acceptJson()->timeout(30)->retry(2, 500)->get($url)->throw()->json();
            $row = data_get($response, 'Results.0');
            throw_unless(is_array($row), RuntimeException::class, 'vPIC returned no decodable result.');

            return $row;
        });

        $make = trim((string) ($decoded['Make'] ?? ''));
        $model = trim((string) ($decoded['Model'] ?? ''));
        $year = (int) ($decoded['ModelYear'] ?? 0);

        if ($make === '' || $model === '' || $year === 0) {
            return new VinResolutionResult('basic_only', null, 45, decoded: $this->publicDecoded($decoded), missing: ['make/model/year']);
        }

        $query = VehicleConfiguration::query()
            ->with(['generation.model.make', 'engine'])
            ->where('year', $year)
            ->whereHas('generation.model.make', fn ($q) => $q->where('name', 'ilike', $make))
            ->whereHas('generation.model', fn ($q) => $q->where('name', 'ilike', $model));

        $matches = $query->limit(20)->get();
        if ($matches->count() === 1) {
            return new VinResolutionResult('high_confidence', $matches->first()->id, 88, decoded: $this->publicDecoded($decoded));
        }

        if ($matches->isNotEmpty()) {
            return new VinResolutionResult(
                'ambiguous',
                null,
                65,
                decoded: $this->publicDecoded($decoded),
                candidates: $matches->map(fn ($vehicle) => [
                    'id' => $vehicle->id,
                    'make' => $vehicle->generation?->model?->make?->name,
                    'model' => $vehicle->generation?->model?->name,
                    'generation' => $vehicle->generation?->name,
                    'engine' => $vehicle->engine?->name,
                    'year' => $vehicle->year,
                ])->values()->all(),
                missing: ['engine/configuration discriminator'],
            );
        }

        return new VinResolutionResult('basic_only', null, 55, decoded: $this->publicDecoded($decoded), missing: ['canonical vehicle mapping']);
    }

    private function publicDecoded(array $decoded): array
    {
        $keys = [
            'Make', 'Model', 'ModelYear', 'Manufacturer', 'VehicleType', 'BodyClass',
            'EngineModel', 'DisplacementL', 'FuelTypePrimary', 'EngineHP', 'DriveType',
            'TransmissionStyle', 'PlantCountry', 'Series', 'Trim', 'ErrorCode', 'ErrorText',
        ];

        return collect($keys)->mapWithKeys(fn ($key) => [$key => $decoded[$key] ?? null])->filter(fn ($value) => $value !== null && $value !== '')->all();
    }
}
