<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Http\Controllers\Controller;
use App\Models\CatalogPart;
use Illuminate\Http\JsonResponse;

class PartShowController extends Controller
{
    public function __invoke(string $part, CatalogPublicationScope $scope, CatalogSerializer $serializer): JsonResponse
    {
        $publicId = str_starts_with($part, 'prt_') ? substr($part, 4) : $part;
        $query = CatalogPart::query()->where('public_id', $publicId);
        $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');
        $model = $query->firstOrFail();

        return response()->json(['data' => $serializer->part($model)]);
    }
}
