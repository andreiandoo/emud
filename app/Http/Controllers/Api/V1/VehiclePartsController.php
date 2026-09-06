<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Http\Controllers\Controller;
use App\Models\CatalogFitment;
use App\Models\VehicleConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehiclePartsController extends Controller
{
    public function __invoke(int $vehicle, Request $request, CatalogSerializer $serializer): JsonResponse
    {
        VehicleConfiguration::query()->findOrFail($vehicle);
        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $query = CatalogFitment::query()
            ->with(['part.brand', 'part.category', 'part.numbers.brand', 'part.numbers.oeMake'])
            ->where('configuration_id', $vehicle)
            ->whereIn('status', ['confirmed', 'conditional'])
            ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true));

        $query->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
            ->when($request->filled('position'), fn ($q) => $q->where('position', $request->query('position')))
            ->when($request->filled('min_confidence'), fn ($q) => $q->where('confidence', '>=', (float) $request->query('min_confidence')));

        $parts = $query->limit($limit)->get()->pluck('part')->filter()->unique('id')->values()->map(fn ($part) => $serializer->part($part));

        return response()->json(['data' => $parts, 'meta' => ['limit' => $limit]]);
    }
}
