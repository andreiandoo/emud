<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class VpicPostgresVinResolver
{
    public function __construct(
        private readonly VpicDecodedVehicleMatcher $matcher,
        private readonly VpicResultFlattener $flattener,
    ) {}

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

        $connection = (string) ($source->settings['database_connection'] ?? 'pgsql');
        $schema = (string) ($source->settings['database_schema'] ?? 'vpic');
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
            return new VinResolutionResult('unavailable', null, 0, message: 'The local vPIC schema configuration is invalid.');
        }

        try {
            $decoded = Cache::remember(
                'catalog:vin:vpic-pg:'.hash('sha256', $connection.'|'.$schema.'|'.$vin),
                now()->addDay(),
                function () use ($connection, $schema, $vin): array {
                    $rows = DB::connection($connection)->select(
                        "select * from {$schema}.spvindecode(cast(? as varchar))",
                        [$vin],
                    );

                    return $this->flattener->flatten($rows);
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            return new VinResolutionResult('unavailable', null, 0, message: 'The local vPIC decoder is unavailable.');
        }

        if ($decoded === []) {
            return new VinResolutionResult('unavailable', null, 0, message: 'The local vPIC decoder returned no data.');
        }

        return $this->matcher->match($decoded);
    }
}
