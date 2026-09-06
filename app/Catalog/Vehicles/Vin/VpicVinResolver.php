<?php

namespace App\Catalog\Vehicles\Vin;

use App\Models\CatalogSource;

class VpicVinResolver
{
    public function __construct(
        private readonly VpicHttpVinResolver $http,
        private readonly VpicPostgresVinResolver $postgres,
    ) {}

    public function resolve(string $vin, bool $publicContext = false): VinResolutionResult
    {
        $source = CatalogSource::query()->where('code', 'VPIC')->where('is_active', true)->first();
        if (! $source) {
            return new VinResolutionResult('unsupported', null, 0, message: 'vPIC source is not enabled.');
        }

        $mode = (string) ($source->settings['resolver_mode'] ?? 'http');

        return match ($mode) {
            'postgres' => $this->postgres->resolve($vin, $publicContext),
            'postgres_then_http' => $this->withFallback(
                $this->postgres->resolve($vin, $publicContext),
                fn () => $this->http->resolve($vin, $publicContext),
            ),
            'http_then_postgres' => $this->withFallback(
                $this->http->resolve($vin, $publicContext),
                fn () => $this->postgres->resolve($vin, $publicContext),
            ),
            default => $this->http->resolve($vin, $publicContext),
        };
    }

    private function withFallback(VinResolutionResult $primary, callable $fallback): VinResolutionResult
    {
        if ($primary->status !== 'unavailable') {
            return $primary;
        }

        return $fallback();
    }
}
