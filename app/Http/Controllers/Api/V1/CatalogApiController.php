<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiResponseCache;
use App\Catalog\Api\SourceAttribution;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shape every v1 endpoint answers in: `data`, and a `meta` carrying paging and the credit
 * owed to the sources behind the rows.
 */
abstract class CatalogApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function respond(mixed $data, array $meta = [], ?SourceAttribution $attribution = null): JsonResponse
    {
        return response()->json($this->envelope($data, $meta, $attribution));
    }

    /**
     * Serve a read from cache when an identical one was answered under the current catalog
     * version. The callback returns the whole envelope so paging totals and attribution are
     * cached with the rows they describe.
     *
     * @param  callable(): array<string, mixed>  $callback
     */
    protected function cached(Request $request, callable $callback): JsonResponse
    {
        $result = app(ApiResponseCache::class)->remember($request, $callback);

        return response()
            ->json($result['payload'])
            ->header('X-Catalog-Cache', $result['hit'] ? 'hit' : 'miss');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function envelope(mixed $data, array $meta = [], ?SourceAttribution $attribution = null): array
    {
        if ($attribution) {
            $meta = [...$meta, ...$attribution->meta()];
        }

        return $meta === [] ? ['data' => $data] : ['data' => $data, 'meta' => $meta];
    }
}
