<?php

namespace App\Catalog\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache for public catalog reads.
 *
 * What the API may publish is decided by source rights, not by who is asking, so two consumers
 * sending the same query get the same bytes and one entry serves both. The consumer is therefore
 * deliberately absent from the key. Quota and rate-limit headers are written by the middleware
 * onto the live response, not stored here, so a cache hit still reports that caller's own
 * remaining quota.
 */
class ApiResponseCache
{
    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array{payload: array<string, mixed>, hit: bool}
     */
    public function remember(Request $request, callable $callback): array
    {
        if (! config('catalog_api.cache.enabled', true)) {
            return ['payload' => $callback(), 'hit' => false];
        }

        $key = $this->key($request);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return ['payload' => $cached, 'hit' => true];
        }

        $payload = $callback();
        Cache::put($key, $payload, (int) config('catalog_api.cache.ttl', 300));

        return ['payload' => $payload, 'hit' => false];
    }

    private function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return 'catalog:api:'.CatalogVersion::current().':'.sha1($request->path().'?'.http_build_query($query));
    }
}
