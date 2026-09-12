<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartByNumberController extends CatalogApiController
{
    public function __invoke(
        Request $request,
        IdentifierNormalizer $normalizer,
        CatalogSerializer $serializer,
        CatalogPublicationScope $scope,
        SourceAttribution $attribution,
        ?string $number = null,
    ): JsonResponse {
        // Real part numbers contain slashes — Mann sells a `W 68/3` — and a slash cannot survive
        // a path segment even percent-encoded. `/parts/lookup?number=…` is therefore the form
        // that works for every number; the path form stays for the ones where it is prettier.
        $number ??= (string) $request->query('number', '');
        if (trim($number) === '') {
            return response()->json([
                'error' => ['code' => 'NUMBER_REQUIRED', 'message' => 'Pass the part number as ?number= or in the path.'],
            ], 422);
        }

        $paging = ApiPagination::fromRequest($request);

        return $this->cached($request, function () use ($number, $request, $paging, $normalizer, $serializer, $scope, $attribution): array {
            $scheme = $request->filled('scheme') ? strtoupper((string) $request->query('scheme')) : null;

            // A number can reach the same part through more than one row — the same OE number
            // recorded by two sources, or a part that lists a rival's number as both IAM and
            // cross reference. Page over the parts the number resolves to, not the rows.
            $partIds = self::matchingNumbers($number, $scheme, $normalizer, $scope)->select('catalog_part_id');

            $query = CatalogPart::query()
                ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake'])
                ->whereIn('catalog_parts.id', $partIds->toBase())
                ->orderBy('catalog_parts.id');

            $page = $paging->paginate($query);
            $attribution->addParts($page['items']);

            return $this->envelope(
                $page['items']->map(fn ($part) => $serializer->part($part))->all(),
                [...$page['meta'], 'query' => array_filter(['number' => $number, 'scheme' => $scheme])],
                $attribution,
            );
        });
    }

    /**
     * Rows whose number matches, restricted to evidence this API may republish and to parts that
     * are themselves published. Shared with the batch endpoint so both apply the same boundary.
     */
    public static function matchingNumbers(
        string $number,
        ?string $scheme,
        IdentifierNormalizer $normalizer,
        CatalogPublicationScope $scope,
    ) {
        return CatalogPartNumber::query()
            ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true))
            ->whereHas('part', fn ($partQuery) => $scope->visibleEntity($partQuery, 'catalog_part', 'catalog_parts.id'))
            ->where(fn ($q) => $q
                ->where('number_compact', $normalizer->compact($number))
                ->orWhere('number_normalized', $normalizer->normalize($number)))
            ->when($scheme !== null, fn ($q) => $q->where('scheme', $scheme));
    }
}
