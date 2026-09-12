<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Api\SourceAttribution;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Resolve many numbers in one call.
 *
 * A shop matching its own catalogue against this one has thousands of numbers to look up, and
 * doing that one HTTP request at a time is the difference between a job that finishes overnight
 * and one that does not finish. The whole batch is answered with two queries regardless of size,
 * so the cost to serve it is far below the cost of the same numbers arriving separately.
 */
class PartNumberBatchController extends CatalogApiController
{
    public function __invoke(
        Request $request,
        IdentifierNormalizer $normalizer,
        CatalogSerializer $serializer,
        CatalogPublicationScope $scope,
        SourceAttribution $attribution,
    ): JsonResponse {
        $max = (int) config('catalog_api.batch.max_items', 50);

        $validated = $request->validate([
            'numbers' => ['required', 'array', 'min:1', 'max:'.$max],
            'numbers.*' => ['required', 'string', 'max:128'],
            'scheme' => ['nullable', 'string', 'max:32'],
        ]);

        $scheme = isset($validated['scheme']) ? strtoupper($validated['scheme']) : null;
        $requested = array_values(array_unique(array_map('strval', $validated['numbers'])));

        $lookup = [];
        foreach ($requested as $number) {
            $lookup[$number] = [
                'compact' => $normalizer->compact($number),
                'normalized' => $normalizer->normalize($number),
            ];
        }

        $compacts = array_values(array_filter(array_column($lookup, 'compact')));
        $normalized = array_values(array_filter(array_column($lookup, 'normalized')));

        $rows = CatalogPartNumber::query()
            ->select(['catalog_part_id', 'number_compact', 'number_normalized'])
            ->whereHas('source', fn ($q) => $q->where('allow_api_redistribution', true))
            ->whereHas('part', fn ($partQuery) => $scope->visibleEntity($partQuery, 'catalog_part', 'catalog_parts.id'))
            ->where(fn ($q) => $q->whereIn('number_compact', $compacts)->orWhereIn('number_normalized', $normalized))
            ->when($scheme !== null, fn ($q) => $q->where('scheme', $scheme))
            ->get();

        $parts = CatalogPart::query()
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->whereIn('id', $rows->pluck('catalog_part_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $attribution->addParts($parts);

        $byCompact = $rows->groupBy('number_compact');
        $byNormalized = $rows->groupBy('number_normalized');

        $data = [];
        $matched = 0;
        foreach ($requested as $number) {
            $partIds = Collection::make()
                ->concat($byCompact->get($lookup[$number]['compact'], []))
                ->concat($byNormalized->get($lookup[$number]['normalized'], []))
                ->pluck('catalog_part_id')
                ->unique()
                ->values();

            $matches = $partIds
                ->map(fn ($id) => $parts->get($id))
                ->filter()
                ->map(fn (CatalogPart $part) => $serializer->part($part))
                ->values()
                ->all();

            if ($matches !== []) {
                $matched++;
            }

            $data[] = ['number' => $number, 'matches' => $matches];
        }

        return $this->respond($data, array_filter([
            'requested' => count($requested),
            'matched' => $matched,
            'max_items' => $max,
            'scheme' => $scheme,
        ], fn ($value) => $value !== null), $attribution);
    }
}
