<?php

namespace App\Catalog\Search;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSearchDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CatalogSearchService
{
    private ?bool $available = null;

    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    /** @return array<int, int>|null */
    public function partIds(string $term, int $limit = 100): ?array
    {
        $normalized = $this->normalizer->normalize($term);
        $compact = $this->normalizer->compact($term);
        $exact = [];

        if ($compact !== '') {
            $exact = CatalogPartNumber::query()
                ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
                ->where(fn ($query) => $query->where('number_compact', $compact)->orWhere('number_normalized', $normalized))
                ->limit($limit)
                ->pluck('catalog_part_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $projected = $this->entityIds('catalog_part', $term, max($limit, min(500, $limit * 5)));
        if ($projected === null && $exact === []) {
            return null;
        }

        return array_slice(array_values(array_unique([...$exact, ...($projected ?? [])])), 0, max($limit, min(500, $limit * 5)));
    }

    /** @return array<int, int>|null */
    public function vehicleIds(string $term, int $limit = 100): ?array
    {
        return $this->entityIds('vehicle_configuration', $term, max($limit, min(500, $limit * 5)));
    }

    /** @return array<int, int>|null */
    public function entityIds(string $entityType, string $term, int $limit = 100): ?array
    {
        $term = trim($term);
        if ($term === '' || ! $this->isAvailable()) {
            return null;
        }

        $query = CatalogSearchDocument::query()->where('entity_type', $entityType);
        if (! (clone $query)->exists()) {
            return null;
        }

        $limit = min(500, max(1, $limit));
        if (DB::getDriverName() === 'pgsql') {
            $expression = "to_tsvector('simple', search_text)";
            $query->whereRaw("{$expression} @@ websearch_to_tsquery('simple', ?)", [$term])
                ->orderByRaw("ts_rank_cd({$expression}, websearch_to_tsquery('simple', ?)) DESC", [$term]);
        } else {
            $query->where('search_text', 'like', '%'.$term.'%')->orderBy('id');
        }

        return $query->limit($limit)->pluck('entity_id')->map(fn ($id) => (int) $id)->all();
    }

    public function constrainAndRank(Builder $query, array $ids, string $qualifiedId): Builder
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $qualifiedId)) {
            throw new InvalidArgumentException('Unsafe qualified ID column.');
        }
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $bindings = [];
        $cases = [];
        foreach ($ids as $rank => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $rank;
        }

        return $query
            ->whereIn($qualifiedId, $ids)
            ->orderByRaw('CASE '.$qualifiedId.' '.implode(' ', $cases).' ELSE '.count($ids).' END', $bindings);
    }

    private function isAvailable(): bool
    {
        return $this->available ??= Schema::hasTable('catalog_search_documents');
    }
}
