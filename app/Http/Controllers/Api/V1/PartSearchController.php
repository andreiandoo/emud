<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Http\Controllers\Controller;
use App\Models\CatalogPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartSearchController extends Controller
{
    public function __invoke(Request $request, CatalogPublicationScope $scope, CatalogSerializer $serializer, IdentifierNormalizer $normalizer): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $compact = $normalizer->compact($term);
        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $query = CatalogPart::query()->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake']);
        $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');

        $query->when($term !== '', function ($query) use ($term, $compact): void {
            $query->where(function ($query) use ($term, $compact): void {
                $query->where('mpn_normalized', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                    ->orWhereHas('numbers', fn ($q) => $q->where('number_normalized', 'ilike', "%{$term}%")->orWhere('number_compact', 'ilike', "%{$compact}%"));
            });
        });

        $query->when($request->filled('brand'), fn ($q) => $q->whereHas('brand', fn ($b) => $b->where('name', 'ilike', $request->query('brand'))))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')));

        $data = $query->limit($limit)->get()->map(fn ($part) => $serializer->part($part));

        return response()->json(['data' => $data, 'meta' => ['limit' => $limit]]);
    }
}
