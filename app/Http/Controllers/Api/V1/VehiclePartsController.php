<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehiclePartsController extends CatalogApiController
{
    public function __invoke(
        int $vehicle,
        Request $request,
        CatalogSerializer $serializer,
        CatalogPublicationScope $scope,
        SourceAttribution $attribution,
    ): JsonResponse {
        $paging = ApiPagination::fromRequest($request);

        return $this->cached($request, function () use ($vehicle, $request, $paging, $serializer, $scope, $attribution): array {
            $vehicleQuery = VehicleConfiguration::query();
            $scope->visibleEntity($vehicleQuery, 'vehicle_configuration', 'vehicle_configurations.id');
            $vehicleQuery->findOrFail($vehicle);

            // One part can be fitted to one vehicle by several rows — a left and a right
            // position, or two sources agreeing. Paging over the fitments would repeat that part
            // across pages and make every page short; page over the distinct parts instead.
            $fitments = CatalogFitment::query()
                ->select('catalog_part_id')
                ->where('configuration_id', $vehicle)
                ->whereIn('status', ['confirmed', 'conditional'])
                ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true))
                ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
                ->when($request->filled('position'), fn ($q) => $q->where('position', $request->query('position')))
                ->when($request->filled('min_confidence'), fn ($q) => $q->where('confidence', '>=', (float) $request->query('min_confidence')));

            $query = CatalogPart::query()
                ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake'])
                ->whereIn('catalog_parts.id', $fitments->toBase())
                ->orderBy('catalog_parts.id');
            $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');

            $page = $paging->paginate($query);
            $attribution->addParts($page['items'])->addEntities('vehicle_configuration', [$vehicle]);

            return $this->envelope(
                $page['items']->map(fn ($part) => $serializer->part($part))->all(),
                $page['meta'],
                $attribution,
            );
        });
    }
}
