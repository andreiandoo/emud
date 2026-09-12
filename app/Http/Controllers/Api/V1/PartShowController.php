<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Models\CatalogPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartShowController extends CatalogApiController
{
    public function __invoke(
        string $part,
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        SourceAttribution $attribution,
    ): JsonResponse {
        return $this->cached($request, function () use ($part, $scope, $serializer, $attribution): array {
            $publicId = str_starts_with($part, 'prt_') ? substr($part, 4) : $part;
            $query = CatalogPart::query()
                ->where('public_id', $publicId)
                ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source']);
            $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');
            $model = $query->firstOrFail();

            $attribution->addParts([$model]);

            return $this->envelope($serializer->part($model), [], $attribution);
        });
    }
}
