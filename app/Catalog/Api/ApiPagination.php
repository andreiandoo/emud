<?php

namespace App\Catalog\Api;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One paging contract for every list endpoint.
 *
 * `limit` was the only control the first version shipped, and consumers already send it, so it
 * stays as an alias for `per_page` instead of becoming a second knob that means almost the same
 * thing. A caller that sends both gets `per_page`.
 */
final class ApiPagination
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 25;

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly bool $withTotal,
    ) {}

    public static function fromRequest(Request $request, int $default = self::DEFAULT_PER_PAGE): self
    {
        $perPage = $request->has('per_page') ? $request->query('per_page') : $request->query('limit', $default);

        return new self(
            page: max(1, (int) $request->query('page', 1)),
            perPage: min(self::MAX_PER_PAGE, max(1, (int) $perPage)),
            // Counting is a second query over the same filters. It is on by default because a
            // storefront needs it to draw a pager, and off for callers that only walk forward.
            withTotal: $request->boolean('with_total', true),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Read one page. One row beyond the page is fetched so `has_more` is known even when the
     * caller asked for no total.
     *
     * @return array{items: Collection, meta: array<string, mixed>}
     */
    public function paginate(BuilderContract $query, ?int $total = null): array
    {
        if ($this->withTotal && $total === null) {
            // reorder() drops the ORDER BY and its bindings; a ranked search orders by a raw
            // CASE expression that a COUNT query has no column list for.
            $total = (clone $query)->reorder()->count();
        }

        $rows = $query->offset($this->offset())->limit($this->perPage + 1)->get();
        $hasMore = $rows->count() > $this->perPage;

        return [
            'items' => $rows->take($this->perPage)->values(),
            'meta' => $this->meta($hasMore, $this->withTotal ? $total : null),
        ];
    }

    /** @return array<string, mixed> */
    public function meta(bool $hasMore, ?int $total = null): array
    {
        $meta = [
            'page' => $this->page,
            'per_page' => $this->perPage,
            // Kept so a v1 consumer written against the first release still reads a number it
            // recognises in the same place.
            'limit' => $this->perPage,
            'has_more' => $hasMore,
        ];

        if ($total !== null) {
            $meta['total'] = $total;
            $meta['total_pages'] = (int) ceil($total / $this->perPage);
        }

        return $meta;
    }

    /**
     * How many ranked candidates the search index must return for this page to be complete.
     * The index caps its own answer, so a deep page of a relevance search can run out of
     * candidates before it runs out of matches; `CatalogSearchService` documents that ceiling.
     */
    public function rankedWindow(): int
    {
        return $this->offset() + $this->perPage + 1;
    }
}
