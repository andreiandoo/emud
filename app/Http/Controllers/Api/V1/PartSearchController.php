<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Search\CatalogSearchService;
use App\Catalog\Search\SearchOperators;
use App\Models\CatalogPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartSearchController extends CatalogApiController
{
    public function __invoke(
        Request $request,
        CatalogPublicationScope $scope,
        CatalogSerializer $serializer,
        IdentifierNormalizer $normalizer,
        CatalogSearchService $search,
        SourceAttribution $attribution,
    ): JsonResponse {
        $term = trim((string) $request->query('q', ''));
        $paging = ApiPagination::fromRequest($request);

        return $this->cached($request, function () use ($request, $term, $paging, $scope, $serializer, $normalizer, $search, $attribution): array {
            $compact = $normalizer->compact($term);
            $like = SearchOperators::like();

            $query = CatalogPart::query()->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake']);
            $scope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');

            if ($term !== '') {
                $ids = $search->partIds($term, $paging->rankedWindow());
                if ($ids !== null) {
                    $search->constrainAndRank($query, $ids, 'catalog_parts.id');
                } else {
                    $query->where(function ($query) use ($term, $compact, $like): void {
                        $query->where('name', $like, "%{$term}%")
                            ->orWhereHas('brand', fn ($brand) => $brand->where('name', $like, "%{$term}%"))
                            ->orWhereHas('numbers', function ($numbers) use ($term, $compact, $like): void {
                                $numbers->whereHas('source', fn ($source) => $source->where('allow_api_redistribution', true))
                                    ->where(fn ($number) => $number
                                        ->where('number_normalized', $like, "%{$term}%")
                                        ->orWhere('number_compact', $like, "%{$compact}%"));
                            });
                    });
                }
            } else {
                $query->orderBy('catalog_parts.id');
            }

            $query->when($request->filled('brand'), fn ($q) => $q->whereHas('brand', fn ($brand) => $brand->where('name', $like, $request->query('brand'))))
                ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
                ->when($request->filled('lifecycle_status'), fn ($q) => $q->where('lifecycle_status', $request->query('lifecycle_status')));

            $page = $paging->paginate($query);
            $attribution->addParts($page['items']);

            return $this->envelope(
                $page['items']->map(fn ($part) => $serializer->part($part))->all(),
                $page['meta'],
                $attribution,
            );
        });
    }
}
