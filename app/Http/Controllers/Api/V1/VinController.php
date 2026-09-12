<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Vehicles\Vin\VinResolutionResult;
use App\Catalog\Vehicles\Vin\VpicVinResolver;
use App\Models\CatalogSource;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VinController extends CatalogApiController
{
    public function __invoke(
        string $vin,
        Request $request,
        VpicVinResolver $resolver,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): JsonResponse {
        // Decoding the same VIN twice is the commonest shape of traffic a storefront produces —
        // one customer reloading a fitment page — and each miss can cost an upstream HTTP call.
        return $this->cached($request, fn (): array => $this->decode($vin, $resolver, $serializer, $attribution));
    }

    /** @return array<string, mixed> */
    private function decode(
        string $vin,
        VpicVinResolver $resolver,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): array {
        $result = $resolver->resolve($vin, publicContext: true);
        $vehicle = $result->vehicleConfigurationId
            ? VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers.source'])->find($result->vehicleConfigurationId)
            : null;

        if ($vehicle) {
            $attribution->addVehicles([$vehicle]);
        }

        if ($result->status !== 'unsupported') {
            $attribution->addSources(
                CatalogSource::query()->where('code', 'VPIC')->where('allow_api_redistribution', true)->pluck('id'),
            );
        }

        return $this->envelope([
            'vin' => strtoupper($vin),
            'resolution' => $result->status,
            'confidence' => $result->confidence,
            'vehicle' => $vehicle ? $serializer->vehicle($vehicle) : null,
            // What the VIN did settle when it could not settle a build. A storefront uses this to
            // pre-select its car picker instead of starting the customer at the top of the tree.
            'narrowed_to' => $this->narrowedTo($result),
            'decoded' => $result->decoded,
            'candidates' => $result->candidates,
            'missing_for_exact_fitment' => $result->missing,
            'message' => $result->message,
        ], [], $attribution);
    }

    /** @return array<string, mixed>|null */
    private function narrowedTo(VinResolutionResult $result): ?array
    {
        if ($result->makeId === null && $result->modelId === null && $result->generationId === null) {
            return null;
        }

        $make = $result->makeId ? VehicleMake::query()->find($result->makeId) : null;
        $model = $result->modelId ? VehicleModel::query()->find($result->modelId) : null;
        $generation = $result->generationId ? VehicleGeneration::query()->find($result->generationId) : null;

        return array_filter([
            'make' => $make ? ['id' => 'mk_'.$make->id, 'name' => $make->name] : null,
            'model' => $model ? ['id' => 'mdl_'.$model->id, 'name' => $model->name] : null,
            'generation' => $generation ? [
                'id' => 'gen_'.$generation->id,
                'name' => $generation->name,
                'year_from' => $generation->year_from,
                'year_to' => $generation->year_to,
            ] : null,
        ], fn ($value) => $value !== null);
    }
}
