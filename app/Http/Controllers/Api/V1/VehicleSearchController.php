<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Search\CatalogSearchService;
use App\Catalog\Search\SearchOperators;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleSearchController extends CatalogApiController
{
    public function __invoke(
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        CatalogSearchService $search,
        SourceAttribution $attribution,
    ): JsonResponse {
        $term = trim((string) $request->query('q', ''));
        $paging = ApiPagination::fromRequest($request);

        return $this->cached($request, function () use ($request, $term, $paging, $scope, $serializer, $search, $attribution): array {
            $query = VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers']);
            $scope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');

            if ($term !== '') {
                // Ask the index for enough candidates to reach the requested page, not just one
                // page worth, or page 3 of a ranked search would be built from page 1's rows.
                $ids = $search->vehicleIds($term, $paging->rankedWindow());
                if ($ids !== null) {
                    $search->constrainAndRank($query, $ids, 'vehicle_configurations.id');
                } else {
                    $like = SearchOperators::like();
                    $query->where(function ($query) use ($term, $like): void {
                        $query->where('commercial_name', $like, "%{$term}%")
                            ->orWhere('eu_type_approval', $like, "%{$term}%")
                            ->orWhereHas('generation.model', fn ($model) => $model->where('name', $like, "%{$term}%"))
                            ->orWhereHas('generation.model.make', fn ($make) => $make->where('name', $like, "%{$term}%"))
                            ->orWhereHas('engine', fn ($engine) => $engine->where('engine_code', $like, "%{$term}%"));
                    });
                }
            } else {
                $query->orderBy('vehicle_configurations.id');
            }

            $like = SearchOperators::like();
            $query->when($request->filled('make'), fn ($q) => $q->whereHas('generation.model.make', fn ($make) => $make->where('name', $like, $request->query('make'))))
                ->when($request->filled('model'), fn ($q) => $q->whereHas('generation.model', fn ($model) => $model->where('name', $like, $request->query('model'))))
                ->when($request->filled('year'), fn ($q) => $q->where('year', (int) $request->query('year')))
                ->when($request->filled('fuel'), fn ($q) => $q->where('fuel_type', $request->query('fuel')))
                ->when($request->filled('market'), fn ($q) => $q->where('market', $request->query('market')));

            $page = $paging->paginate($query);
            $attribution->addVehicles($page['items']);

            return $this->envelope(
                $page['items']->map(fn ($vehicle) => $serializer->vehicle($vehicle))->all(),
                $page['meta'],
                $attribution,
            );
        });
    }
}
