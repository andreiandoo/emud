<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class VpicHttpVinResolver
{
    public function __construct(private readonly VpicDecodedVehicleMatcher $matcher) {}

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

        try {
            $decoded = Cache::remember('catalog:vin:vpic:'.hash('sha256', $vin), now()->addDay(), function () use ($source, $vin): array {
                $base = rtrim((string) ($source->settings['api_base_url'] ?? $source->base_url ?? 'https://vpic.nhtsa.dot.gov'), '/');
                $url = $base.'/api/vehicles/DecodeVinValues/'.rawurlencode($vin).'?format=json';
                $response = Http::acceptJson()->timeout(30)->retry(2, 500)->get($url)->throw()->json();
                $row = data_get($response, 'Results.0');
                throw_unless(is_array($row), RuntimeException::class, 'vPIC returned no decodable result.');

                return $row;
            });
        } catch (Throwable $exception) {
            report($exception);

            return new VinResolutionResult('unavailable', null, 0, message: 'The vPIC HTTP decoder is unavailable.');
        }

        return $this->matcher->match($decoded, $publicContext, $vin);
    }
}
