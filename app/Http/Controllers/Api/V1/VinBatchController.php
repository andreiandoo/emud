<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Vehicles\Vin\VpicVinResolver;
use App\Models\CatalogSource;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Decode a list of VINs in one call — what a shop needs when it imports a fleet or a customer
 * list, rather than one car at a time from a product page.
 */
class VinBatchController extends CatalogApiController
{
    private const VIN = '/^[A-HJ-NPR-Z0-9]{17}$/';

    public function __invoke(
        Request $request,
        VpicVinResolver $resolver,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): JsonResponse {
        $max = (int) config('catalog_api.batch.max_items', 50);

        $validated = $request->validate([
            'vins' => ['required', 'array', 'min:1', 'max:'.$max],
            'vins.*' => ['required', 'string'],
        ]);

        $vins = array_values(array_unique(array_map(
            fn ($vin) => strtoupper(trim((string) $vin)),
            $validated['vins'],
        )));

        $decoded = 0;
        $data = [];
        foreach ($vins as $vin) {
            // A malformed VIN is reported in its own row rather than failing the batch: one bad
            // row in a customer's spreadsheet should not cost them the other forty-nine.
            if (preg_match(self::VIN, $vin) !== 1) {
                $data[] = [
                    'vin' => $vin,
                    'resolution' => 'invalid',
                    'confidence' => 0,
                    'vehicle' => null,
                    'message' => 'Not a 17-character VIN.',
                ];

                continue;
            }

            $result = $resolver->resolve($vin, publicContext: true);
            $vehicle = $result->vehicleConfigurationId
                ? VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers.source'])->find($result->vehicleConfigurationId)
                : null;

            if ($vehicle) {
                $attribution->addVehicles([$vehicle]);
            }
            if ($result->status !== 'unsupported') {
                $decoded++;
            }

            $data[] = [
                'vin' => $vin,
                'resolution' => $result->status,
                'confidence' => $result->confidence,
                'vehicle' => $vehicle ? $serializer->vehicle($vehicle) : null,
                'decoded' => $result->decoded,
                'missing_for_exact_fitment' => $result->missing,
                'message' => $result->message,
            ];
        }

        if ($decoded > 0) {
            $attribution->addSources(
                CatalogSource::query()->where('code', 'VPIC')->where('allow_api_redistribution', true)->pluck('id'),
            );
        }

        return $this->respond($data, [
            'requested' => count($vins),
            'resolved' => collect($data)->where('resolution', 'high_confidence')->count(),
            'max_items' => $max,
        ], $attribution);
    }
}
