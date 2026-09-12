<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CatalogSourceAssertion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a buyer checks before subscribing: how much of the catalogue this key can actually see.
 */
class CoverageController extends CatalogApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->cached($request, function (): array {
            $visible = CatalogSourceAssertion::query()->where('status', 'published')->where('api_redistributable', true);

            $counts = (clone $visible)
                ->selectRaw('entity_type, count(distinct entity_id) as aggregate')
                ->groupBy('entity_type')
                ->pluck('aggregate', 'entity_type');

            $bySource = (clone $visible)
                ->join('catalog_sources', 'catalog_sources.id', '=', 'catalog_source_assertions.catalog_source_id')
                ->selectRaw('catalog_sources.code as code, catalog_source_assertions.entity_type as entity_type, count(distinct catalog_source_assertions.entity_id) as aggregate')
                ->groupBy('catalog_sources.code', 'catalog_source_assertions.entity_type')
                ->get()
                ->groupBy('code')
                ->map(fn ($rows) => $rows->pluck('aggregate', 'entity_type')->map(fn ($count) => (int) $count))
                ->toArray();

            return [
                'data' => [
                    'vehicle_configurations' => (int) ($counts['vehicle_configuration'] ?? 0),
                    'vehicle_generations' => (int) ($counts['vehicle_generation'] ?? 0),
                    'parts' => (int) ($counts['catalog_part'] ?? 0),
                    'by_source' => $bySource,
                    'generated_at' => now()->toIso8601String(),
                ],
            ];
        });
    }
}
