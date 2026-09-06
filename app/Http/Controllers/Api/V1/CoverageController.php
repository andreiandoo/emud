<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogSourceAssertion;
use Illuminate\Http\JsonResponse;

class CoverageController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $visible = CatalogSourceAssertion::query()->where('status', 'published')->where('api_redistributable', true);

        $counts = (clone $visible)
            ->selectRaw('entity_type, count(distinct entity_id) as aggregate')
            ->groupBy('entity_type')
            ->pluck('aggregate', 'entity_type');

        return response()->json(['data' => [
            'vehicle_configurations' => (int) ($counts['vehicle_configuration'] ?? 0),
            'vehicle_generations' => (int) ($counts['vehicle_generation'] ?? 0),
            'parts' => (int) ($counts['catalog_part'] ?? 0),
            'generated_at' => now()->toIso8601String(),
        ]]);
    }
}
