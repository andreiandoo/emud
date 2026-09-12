<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CatalogSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the data comes from and on what terms.
 *
 * A shop that republishes this catalogue on its own product pages inherits the licence
 * conditions attached to it, so the conditions have to be readable by the integration rather
 * than buried in a contract. Only sources cleared for redistribution are listed: which
 * restricted catalogues the platform holds internally is not a consumer's business.
 */
class SourceIndexController extends CatalogApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->cached($request, function (): array {
            $sources = CatalogSource::query()
                ->where('is_active', true)
                ->where('allow_api_redistribution', true)
                ->orderBy('code')
                ->get();

            return [
                'data' => $sources->map(fn (CatalogSource $source) => array_filter([
                    'code' => $source->code,
                    'name' => $source->name,
                    'type' => $source->source_type,
                    'license' => $source->license_name,
                    'license_url' => $source->license_url,
                    'url' => $source->base_url,
                    'attribution_required' => (bool) $source->attribution_required,
                    'notes' => $source->legal_notes,
                    'capabilities' => array_keys(array_filter((array) ($source->capabilities ?? []))),
                ], fn ($value) => $value !== null && $value !== '' && $value !== []))->values()->all(),
                'meta' => ['total' => $sources->count()],
            ];
        });
    }
}
