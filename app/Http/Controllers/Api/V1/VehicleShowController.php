<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleShowController extends CatalogApiController
{
    public function __invoke(
        int $vehicle,
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): JsonResponse {
        return $this->cached($request, function () use ($vehicle, $scope, $serializer, $attribution): array {
            $query = VehicleConfiguration::query()->whereKey($vehicle)->with(['generation.model.make', 'engine', 'identifiers.source']);
            $scope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');
            $model = $query->firstOrFail();

            $attribution->addVehicles([$model]);

            return $this->envelope($serializer->vehicle($model), [], $attribution);
        });
    }
}
