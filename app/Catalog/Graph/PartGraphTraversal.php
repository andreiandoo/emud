<?php

namespace App\Catalog\Graph;

use App\Catalog\Api\CatalogPublicationScope;
use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use Illuminate\Support\Collection;

class PartGraphTraversal
{
    public function __construct(
        private readonly IdentifierNormalizer $normalizer,
        private readonly CatalogPublicationScope $publicationScope,
        private readonly CatalogSerializer $serializer,
    ) {}

    public function byNumber(
        string $number,
        ?string $scheme = null,
        int $depth = 2,
        int $maxNodes = 100,
    ): array {
        $depth = max(0, min($depth, 4));
        $maxNodes = max(1, min($maxNodes, 250));
        $compact = $this->normalizer->compact($number);
        $normalized = $this->normalizer->normalize($number);

        $seedQuery = CatalogPartNumber::query()
            ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
            ->where(function ($query) use ($compact, $normalized): void {
                $query->where('number_compact', $compact)
                    ->orWhere('number_normalized', $normalized);
            });

        if ($scheme) {
            $seedQuery->where('scheme', strtoupper($scheme));
        }

        $seedIds = $seedQuery->pluck('catalog_part_id')->unique()->values();
        $seedIds = $this->visiblePartIds($seedIds)->values();

        if ($seedIds->isEmpty()) {
            return [
                'query' => ['number' => $number, 'scheme' => $scheme, 'depth' => $depth],
                'seeds' => [],
                'nodes' => [],
                'edges' => [],
                'truncated' => false,
            ];
        }

        $visited = $seedIds->mapWithKeys(fn ($id) => [(int) $id => 0])->all();
        $frontier = $seedIds->map(fn ($id) => (int) $id)->all();
        $edges = [];
        $edgeKeys = [];
        $truncated = false;

        for ($level = 1; $level <= $depth && $frontier !== []; $level++) {
            $relations = CatalogPartRelation::query()
                ->with('source')
                ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
                ->where(function ($query) use ($frontier): void {
                    $query->whereIn('source_part_id', $frontier)
                        ->orWhereIn('target_part_id', $frontier);
                })
                ->orderByDesc('confidence')
                ->limit($maxNodes * 8)
                ->get();

            $candidateIds = collect();
            foreach ($relations as $relation) {
                $candidateIds->push((int) $relation->source_part_id, (int) $relation->target_part_id);
            }

            $visibleIds = $this->visiblePartIds($candidateIds->unique())->flip();
            $nextFrontier = [];

            foreach ($relations as $relation) {
                $sourceId = (int) $relation->source_part_id;
                $targetId = (int) $relation->target_part_id;
                if (! isset($visibleIds[$sourceId]) || ! isset($visibleIds[$targetId])) {
                    continue;
                }

                $edgeKey = implode(':', [$sourceId, $targetId, $relation->relation_type, $relation->catalog_source_id]);
                if (! isset($edgeKeys[$edgeKey])) {
                    $edges[] = [
                        'from' => 'prt_'.$this->publicId($sourceId),
                        'to' => 'prt_'.$this->publicId($targetId),
                        'relation_type' => $relation->relation_type,
                        'directed' => (bool) $relation->is_directed,
                        'confidence' => $relation->confidence !== null ? (float) $relation->confidence : null,
                        'source' => $relation->source?->code,
                    ];
                    $edgeKeys[$edgeKey] = true;
                }

                foreach ([$sourceId, $targetId] as $partId) {
                    if (isset($visited[$partId])) {
                        continue;
                    }
                    if (count($visited) >= $maxNodes) {
                        $truncated = true;
                        break 2;
                    }
                    $visited[$partId] = $level;
                    $nextFrontier[] = $partId;
                }
            }

            $frontier = array_values(array_unique($nextFrontier));
        }

        $parts = CatalogPart::query()
            ->whereIn('id', array_keys($visited))
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->get()
            ->keyBy('id');

        $nodes = collect($visited)
            ->map(function (int $distance, int|string $partId) use ($parts): ?array {
                $part = $parts->get((int) $partId);
                if (! $part) {
                    return null;
                }

                return [
                    'distance' => $distance,
                    'part' => $this->serializer->part($part),
                ];
            })
            ->filter()
            ->sortBy(fn (array $node) => [$node['distance'], $node['part']['brand'] ?? '', $node['part']['mpn'] ?? ''])
            ->values()
            ->all();

        return [
            'query' => ['number' => $number, 'scheme' => $scheme, 'depth' => $depth],
            'seeds' => $seedIds->map(fn ($id) => 'prt_'.$this->publicId((int) $id))->all(),
            'nodes' => $nodes,
            'edges' => $edges,
            'truncated' => $truncated,
        ];
    }

    private function visiblePartIds(Collection $ids): Collection
    {
        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $query = CatalogPart::query()->whereIn('id', $ids);
        $this->publicationScope->visibleEntity($query, 'catalog_part', 'catalog_parts.id');

        return $query->pluck('id')->map(fn ($id) => (int) $id);
    }

    private function publicId(int $partId): string
    {
        return (string) CatalogPart::query()->whereKey($partId)->value('public_id');
    }
}
