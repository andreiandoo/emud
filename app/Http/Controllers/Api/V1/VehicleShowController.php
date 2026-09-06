<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Http\Controllers\Controller;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;

class VehicleShowController extends Controller
{
    public function __invoke(int $vehicle, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $query = VehicleConfiguration::query()->whereKey($vehicle);
        $scope->visibleEntity($query, 'vehicle_configuration', 'vehicle_configurations.id');
        $model = $query->firstOrFail();

        return response()->json(['data' => $serializer->vehicle($model)]);
    }
}
