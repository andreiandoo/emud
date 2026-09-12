<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Search\SearchOperators;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The four levels a storefront's car picker walks: make → model → generation → vehicle.
 *
 * Every level only lists branches that end in a vehicle this API may publish, so a picker built
 * on it never offers a choice that leads nowhere.
 */
class VehicleTreeController extends CatalogApiController
{
    public function makes(Request $request, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $paging = ApiPagination::fromRequest($request, default: 100);

        return $this->cached($request, function () use ($request, $paging, $scope, $serializer): array {
            $query = VehicleMake::query()->where('is_active', true)->orderBy('name');
            $scope->withVisibleConfigurations($query, 'models.generations.configurations');

            if ($request->filled('q')) {
                $query->where('name', SearchOperators::like(), '%'.$request->query('q').'%');
            }

            $page = $paging->paginate($query);

            return $this->envelope(
                $page['items']->map(fn (VehicleMake $make) => $serializer->make($make))->all(),
                $page['meta'],
            );
        });
    }

    public function models(int $make, Request $request, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $paging = ApiPagination::fromRequest($request, default: 100);

        return $this->cached($request, function () use ($make, $request, $paging, $scope, $serializer): array {
            $parent = VehicleMake::query()->where('is_active', true)->findOrFail($make);

            $query = VehicleModel::query()->where('make_id', $parent->id)->where('is_active', true)->orderBy('name');
            $scope->withVisibleConfigurations($query, 'generations.configurations');

            if ($request->filled('q')) {
                $query->where('name', SearchOperators::like(), '%'.$request->query('q').'%');
            }

            $page = $paging->paginate($query);

            return $this->envelope(
                $page['items']->map(fn (VehicleModel $model) => $serializer->model($model))->all(),
                [...$page['meta'], 'make' => $serializer->make($parent)],
            );
        });
    }

    public function generations(int $model, Request $request, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $paging = ApiPagination::fromRequest($request, default: 100);

        return $this->cached($request, function () use ($model, $request, $paging, $scope, $serializer): array {
            $parent = VehicleModel::query()->with('make')->where('is_active', true)->findOrFail($model);

            $query = VehicleGeneration::query()->where('model_id', $parent->id)->orderBy('year_from')->orderBy('name');
            $scope->withVisibleConfigurations($query, 'configurations');

            // A customer knows the year of their car long before they know which generation that
            // lands in, so the year is a filter on the level above the one it belongs to.
            if ($request->filled('year')) {
                $year = (int) $request->query('year');
                $query->where('year_from', '<=', $year)
                    ->where(fn ($q) => $q->whereNull('year_to')->orWhere('year_to', '>=', $year));
            }

            $page = $paging->paginate($query);

            return $this->envelope(
                $page['items']->map(fn (VehicleGeneration $generation) => $serializer->generation($generation))->all(),
                [...$page['meta'], 'model' => $serializer->model($parent)],
            );
        });
    }

    public function vehicles(
        int $generation,
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): JsonResponse {
        $paging = ApiPagination::fromRequest($request, default: 100);

        return $this->cached($request, function () use ($generation, $request, $paging, $scope, $serializer, $attribution): array {
            $parent = VehicleGeneration::query()->with('model.make')->findOrFail($generation);

            $query = VehicleConfiguration::query()
                ->with(['generation.model.make', 'engine', 'identifiers.source'])
                ->where('generation_id', $parent->id)
                ->orderBy('year')
                ->orderBy('vehicle_configurations.id');
            $scope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');

            $query->when($request->filled('year'), fn ($q) => $q->where('year', (int) $request->query('year')))
                ->when($request->filled('fuel'), fn ($q) => $q->where('fuel_type', $request->query('fuel')))
                ->when($request->filled('body_type'), fn ($q) => $q->where('body_type', $request->query('body_type')));

            $page = $paging->paginate($query);
            $attribution->addVehicles($page['items']);

            return $this->envelope(
                $page['items']->map(fn (VehicleConfiguration $vehicle) => $serializer->vehicle($vehicle))->all(),
                [...$page['meta'], 'generation' => $serializer->generation($parent)],
                $attribution,
            );
        });
    }
}
