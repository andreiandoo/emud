<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Vehicles\Vin\VpicHttpVinResolver;
use App\Http\Controllers\Controller;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;

class VinController extends Controller
{
    public function __invoke(string $vin, VpicHttpVinResolver $resolver, CatalogSerializer $serializer): JsonResponse
    {
        $result = $resolver->resolve($vin, publicContext: true);
        $vehicle = $result->vehicleConfigurationId
            ? VehicleConfiguration::query()->find($result->vehicleConfigurationId)
            : null;

        return response()->json(['data' => [
            'vin' => strtoupper($vin),
            'resolution' => $result->status,
            'confidence' => $result->confidence,
            'vehicle' => $vehicle ? $serializer->vehicle($vehicle) : null,
            'decoded' => $result->decoded,
            'candidates' => $result->candidates,
            'missing_for_exact_fitment' => $result->missing,
            'message' => $result->message,
        ]]);
    }
}
