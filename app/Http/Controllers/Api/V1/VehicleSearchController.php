<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Http\Controllers\Controller;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleSearchController extends Controller
{
    public function __invoke(Request $request, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $query = VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers']);
        $scope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');

        $query->when($term !== '', function ($query) use ($term): void {
            $query->where(function ($query) use ($term): void {
                $query->where('commercial_name', 'ilike', "%{$term}%")
                    ->orWhere('eu_type_approval', 'ilike', "%{$term}%")
                    ->orWhereHas('generation.model', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('generation.model.make', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('engine', fn ($q) => $q->where('engine_code', 'ilike', "%{$term}%"));
            });
        });

        $query->when($request->filled('year'), fn ($q) => $q->where('year', (int) $request->query('year')))
            ->when($request->filled('fuel'), fn ($q) => $q->where('fuel_type', $request->query('fuel')))
            ->when($request->filled('market'), fn ($q) => $q->where('market', $request->query('market')));

        $data = $query->limit($limit)->get()->map(fn ($vehicle) => $serializer->vehicle($vehicle));

        return response()->json(['data' => $data, 'meta' => ['limit' => $limit]]);
    }
}
