<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Http\Controllers\Controller;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompatibilityController extends Controller
{
    public function __invoke(Request $request, CatalogPublicationScope $scope): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'part_id' => ['required', 'string'],
        ]);

        $vehicleQuery = VehicleConfiguration::query();
        $scope->visibleEntity($vehicleQuery, 'vehicle_configuration', 'vehicle_configurations.id');
        $vehicle = $vehicleQuery->findOrFail((int) $validated['vehicle_id']);

        $publicId = str_starts_with($validated['part_id'], 'prt_') ? substr($validated['part_id'], 4) : $validated['part_id'];
        $partQuery = CatalogPart::query()->where('public_id', $publicId);
        $scope->visibleEntity($partQuery, 'catalog_part', 'catalog_parts.id');
        $part = $partQuery->firstOrFail();

        $fitment = CatalogFitment::query()
            ->with(['constraints', 'source'])
            ->where('catalog_part_id', $part->id)
            ->where('configuration_id', $vehicle->id)
            ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true))
            ->orderByDesc('confidence')
            ->first();

        if (! $fitment) {
            return response()->json(['data' => ['compatible' => null, 'status' => 'unknown', 'confidence' => null, 'constraints' => []]]);
        }

        $conditional = $fitment->status === 'conditional' || $fitment->constraints->isNotEmpty();

        return response()->json(['data' => [
            'compatible' => in_array($fitment->status, ['confirmed', 'conditional'], true),
            'status' => $conditional ? 'conditional' : ($fitment->status === 'confirmed' ? 'confirmed' : 'unknown'),
            'confidence' => $fitment->confidence,
            'constraints' => $fitment->constraints->map(fn ($constraint) => [
                'type' => $constraint->constraint_type,
                'operator' => $constraint->operator,
                'value' => $constraint->normalized ?? $constraint->value_text ?? $constraint->value_number,
                'display_text' => $constraint->display_text,
            ])->values(),
        ]]);
    }
}
