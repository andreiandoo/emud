<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Search\CatalogSearchService;
use App\Http\Controllers\Controller;
use App\Models\CatalogPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartSearchController extends Controller
{
    public function __invoke(
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        IdentifierNormalizer $normalizer,
        CatalogSearchService $search,
    ): JsonResponse {
        $term = trim((string) $request->query('q', ''));
        $compact = $normalizer->compact($term);
        $limit = min(100, max(1, (int) $request->query('limit', 25)));

        $query = CatalogPart::query()->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake']);
        $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');

        if ($term !== '') {
            $ids = $search->partIds($term, $limit);
            if ($ids !== null) {
                $search->constrainAndRank($query, $ids, 'catalog_parts.id');
            } else {
                $query->where(function ($query) use ($term, $compact): void {
                    $query->where('name', 'ilike', "%{$term}%")
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('numbers', function ($numbers) use ($term, $compact): void {
                            $numbers->whereHas('source', fn ($source) => $source->where('allow_api_redistribution', true))
                                ->where(fn ($number) => $number
                                    ->where('number_normalized', 'ilike', "%{$term}%")
                                    ->orWhere('number_compact', 'ilike', "%{$compact}%"));
                        });
                });
            }
        }

        $query->when($request->filled('brand'), fn ($q) => $q->whereHas('brand', fn ($brand) => $brand->where('name', 'ilike', $request->query('brand'))))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')));

        $data = $query->limit($limit)->get()->map(fn ($part) => $serializer->part($part));

        return response()->json(['data' => $data, 'meta' => ['limit' => $limit]]);
    }
}
