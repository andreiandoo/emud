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
        int $maxEdges = 800,
    ): array {
        $depth = max(0, min($depth, 4));
        $maxNodes = max(1, min($maxNodes, 250));
        $maxEdges = max(1, min($maxEdges, 2000));
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

        $allSeedIds = $this->visiblePartIds($seedQuery->pluck('catalog_part_id')->unique())->values();
        $truncated = $allSeedIds->count() > $maxNodes;
        $seedIds = $allSeedIds->take($maxNodes)->values();

        if ($seedIds->isEmpty()) {
            return [
                'query' => ['number' => $number, 'scheme' => $scheme, 'depth' => $depth, 'max_nodes' => $maxNodes, 'max_edges' => $maxEdges],
                'seeds' => [],
                'nodes' => [],
                'edges' => [],
                'truncated' => false,
            ];
        }

        $visited = $seedIds->mapWithKeys(fn ($id) => [(int) $id => 0])->all();
        $pathConfidence = $seedIds->mapWithKeys(fn ($id) => [(int) $id => 100.0])->all();
        $frontier = $seedIds->map(fn ($id) => (int) $id)->all();
        $rawEdges = [];
        $edgeKeys = [];

        for ($level = 1; $level <= $depth && $frontier !== []; $level++) {
            $frontierLookup = array_fill_keys($frontier, true);
            $fetchLimit = min(5000, max(100, $maxEdges * 4, $maxNodes * 8));
            $relations = CatalogPartRelation::query()
                ->with('source')
                ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
                ->where(function ($query) use ($frontier): void {
                    $query->whereIn('source_part_id', $frontier)
                        ->orWhereIn('target_part_id', $frontier);
                })
                ->orderByDesc('confidence')
                ->orderBy('id')
                ->limit($fetchLimit + 1)
                ->get();

            if ($relations->count() > $fetchLimit) {
                $truncated = true;
                $relations = $relations->take($fetchLimit);
            }

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
                    if (count($rawEdges) >= $maxEdges) {
                        $truncated = true;

                        continue;
                    }

                    $rawEdges[] = [
                        'from_id' => $sourceId,
                        'to_id' => $targetId,
                        'relation_type' => $relation->relation_type,
                        'directed' => (bool) $relation->is_directed,
                        'confidence' => $relation->confidence !== null ? (float) $relation->confidence : null,
                        'source' => $relation->source?->code,
                    ];
                    $edgeKeys[$edgeKey] = true;
                }

                $relationConfidence = $relation->confidence !== null ? (float) $relation->confidence : null;
                $directions = [];
                if (isset($frontierLookup[$sourceId])) {
                    $directions[] = [$sourceId, $targetId];
                }
                if (isset($frontierLookup[$targetId])) {
                    $directions[] = [$targetId, $sourceId];
                }

                foreach ($directions as [$fromId, $candidateId]) {
                    $fromConfidence = (float) ($pathConfidence[$fromId] ?? 100.0);
                    $candidateConfidence = $relationConfidence !== null
                        ? min($fromConfidence, $relationConfidence)
                        : $fromConfidence;

                    if (isset($visited[$candidateId])) {
                        $pathConfidence[$candidateId] = max((float) ($pathConfidence[$candidateId] ?? 0), $candidateConfidence);

                        continue;
                    }

                    if (count($visited) >= $maxNodes) {
                        $truncated = true;

                        continue;
                    }

                    $visited[$candidateId] = $level;
                    $pathConfidence[$candidateId] = $candidateConfidence;
                    $nextFrontier[$candidateId] = true;
                }
            }

            $frontier = array_map('intval', array_keys($nextFrontier));
        }

        $parts = CatalogPart::query()
            ->whereIn('id', array_keys($visited))
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->get()
            ->keyBy('id');
        $publicIds = $parts->mapWithKeys(fn (CatalogPart $part) => [(int) $part->id => 'prt_'.$part->public_id]);

        $nodes = collect($visited)
            ->map(function (int $distance, int|string $partId) use ($parts, $pathConfidence): ?array {
                $part = $parts->get((int) $partId);
                if (! $part) {
                    return null;
                }

                return [
                    'distance' => $distance,
                    'path_confidence' => (float) ($pathConfidence[(int) $partId] ?? 0),
                    'part' => $this->serializer->part($part),
                ];
            })
            ->filter()
            ->sortBy(fn (array $node) => [$node['distance'], $node['part']['brand'] ?? '', $node['part']['mpn'] ?? ''])
            ->values()
            ->all();

        $edges = collect($rawEdges)
            ->filter(fn (array $edge) => $publicIds->has($edge['from_id']) && $publicIds->has($edge['to_id']))
            ->map(fn (array $edge) => [
                'from' => $publicIds->get($edge['from_id']),
                'to' => $publicIds->get($edge['to_id']),
                'relation_type' => $edge['relation_type'],
                'directed' => $edge['directed'],
                'confidence' => $edge['confidence'],
                'source' => $edge['source'],
            ])
            ->values()
            ->all();

        return [
            'query' => [
                'number' => $number,
                'scheme' => $scheme,
                'depth' => $depth,
                'max_nodes' => $maxNodes,
                'max_edges' => $maxEdges,
            ],
            'seeds' => $seedIds->map(fn ($id) => $publicIds->get((int) $id))->filter()->values()->all(),
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
}
