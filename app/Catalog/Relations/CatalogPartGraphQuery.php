<?php

namespace App\Catalog\Relations;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use Illuminate\Support\Collection;

class CatalogPartGraphQuery
{
    public function __construct(private readonly IdentifierNormalizer $normalizer) {}

    /**
     * Resolve an identifier and traverse permissioned canonical part relations.
     *
     * Traversal is breadth-first and batched per depth so graph resolution stays
     * query-bounded even when a component contains many cross-reference edges.
     *
     * @return array{seeds: Collection<int, CatalogPart>, nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, truncated: bool}
     */
    public function resolve(
        string $number,
        ?string $scheme = null,
        int $depth = 2,
        int $maxNodes = 100,
        int $maxEdges = 250,
    ): array {
        $depth = max(0, min($depth, 5));
        $maxNodes = max(1, min($maxNodes, 500));
        $maxEdges = max(1, min($maxEdges, 2000));

        $seeds = $this->seedParts($number, $scheme);
        if ($seeds->isEmpty()) {
            return ['seeds' => $seeds, 'nodes' => [], 'edges' => [], 'truncated' => false];
        }

        $nodes = [];
        $edges = [];
        $visitedDepth = [];
        $truncated = false;
        $frontier = [];

        foreach ($seeds as $part) {
            if (count($nodes) >= $maxNodes) {
                $truncated = true;
                break;
            }

            $nodes[$part->id] = [
                'part' => $part,
                'depth' => 0,
                'path_confidence' => 100.0,
                'seed' => true,
            ];
            $visitedDepth[$part->id] = 0;
            $frontier[$part->id] = 100.0;
        }

        for ($currentDepth = 0; $currentDepth < $depth && $frontier !== []; $currentDepth++) {
            $frontierIds = array_keys($frontier);
            $relations = $this->relationsForParts($frontierIds, max(1000, $maxEdges - count($edges)));
            $candidates = [];

            foreach ($relations as $relation) {
                foreach ($this->traversableDirections($relation, $frontier) as $direction) {
                    if (count($edges) >= $maxEdges) {
                        $truncated = true;
                        break 3;
                    }

                    $fromId = $direction['from'];
                    $nextId = $direction['to'];
                    if ($nextId === $fromId) {
                        continue;
                    }

                    $edgeKey = $relation->id.':'.$fromId;
                    $edges[$edgeKey] ??= [
                        'relation' => $relation,
                        'from_part_id' => $fromId,
                        'to_part_id' => $nextId,
                        'traversal_direction' => $direction['direction'],
                    ];

                    $edgeConfidence = (float) ($relation->confidence ?? 0);
                    $pathConfidence = (float) ($frontier[$fromId] ?? 100.0);
                    $nextConfidence = min($pathConfidence, $edgeConfidence > 0 ? $edgeConfidence : $pathConfidence);
                    $nextDepth = $currentDepth + 1;

                    if (isset($nodes[$nextId])) {
                        $nodes[$nextId]['path_confidence'] = max((float) $nodes[$nextId]['path_confidence'], $nextConfidence);
                        $nodes[$nextId]['depth'] = min((int) $nodes[$nextId]['depth'], $nextDepth);
                    }

                    if (! isset($visitedDepth[$nextId]) || $nextDepth < $visitedDepth[$nextId]) {
                        $candidates[$nextId] = max((float) ($candidates[$nextId] ?? 0), $nextConfidence);
                    }
                }
            }

            if ($candidates === []) {
                break;
            }

            $missingIds = array_values(array_diff(array_keys($candidates), array_keys($nodes)));
            $remainingNodeCapacity = $maxNodes - count($nodes);
            if (count($missingIds) > $remainingNodeCapacity) {
                $truncated = true;
                $missingIds = array_slice($missingIds, 0, max(0, $remainingNodeCapacity));
            }

            $loaded = $this->partsByIds($missingIds)->keyBy('id');
            $nextFrontier = [];
            foreach ($candidates as $nextId => $nextConfidence) {
                if (! isset($nodes[$nextId])) {
                    $part = $loaded->get($nextId);
                    if (! $part) {
                        continue;
                    }

                    $nodes[$nextId] = [
                        'part' => $part,
                        'depth' => $currentDepth + 1,
                        'path_confidence' => $nextConfidence,
                        'seed' => false,
                    ];
                }

                $visitedDepth[$nextId] = $currentDepth + 1;
                $nextFrontier[$nextId] = max((float) ($nextFrontier[$nextId] ?? 0), $nextConfidence);
            }

            $frontier = $nextFrontier;
        }

        $visibleEdges = array_filter(
            $edges,
            fn (array $edge): bool => isset($nodes[$edge['from_part_id']], $nodes[$edge['to_part_id']]),
        );
        if (count($visibleEdges) !== count($edges)) {
            $truncated = true;
        }

        return [
            'seeds' => $seeds,
            'nodes' => array_values($nodes),
            'edges' => array_values($visibleEdges),
            'truncated' => $truncated,
        ];
    }

    /** @return Collection<int, CatalogPart> */
    private function seedParts(string $number, ?string $scheme): Collection
    {
        $normalized = $this->normalizer->normalize($number);
        $compact = $this->normalizer->compact($number);

        return CatalogPartNumber::query()
            ->with(['part.brand', 'part.category', 'part.numbers.brand', 'part.numbers.oeMake', 'part.numbers.source'])
            ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
            ->where(function ($query) use ($normalized, $compact): void {
                $query->where('number_normalized', $normalized)
                    ->orWhere('number_compact', $compact);
            })
            ->when($scheme, fn ($query) => $query->where('scheme', strtoupper($scheme)))
            ->limit(100)
            ->get()
            ->pluck('part')
            ->filter()
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, CatalogPartRelation> */
    private function relationsForParts(array $partIds, int $limit): Collection
    {
        return CatalogPartRelation::query()
            ->with('source')
            ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
            ->where(function ($query) use ($partIds): void {
                $query->whereIn('source_part_id', $partIds)
                    ->orWhereIn('target_part_id', $partIds);
            })
            ->orderByDesc('confidence')
            ->orderBy('id')
            ->limit(min(max($limit, 1), 5000))
            ->get();
    }

    /**
     * @param array<int, float> $frontier
     * @return array<int, array{from:int,to:int,direction:string}>
     */
    private function traversableDirections(CatalogPartRelation $relation, array $frontier): array
    {
        $directions = [];

        if (isset($frontier[$relation->source_part_id])) {
            $directions[] = [
                'from' => $relation->source_part_id,
                'to' => $relation->target_part_id,
                'direction' => 'forward',
            ];
        }

        if (isset($frontier[$relation->target_part_id])) {
            $directions[] = [
                'from' => $relation->target_part_id,
                'to' => $relation->source_part_id,
                'direction' => 'reverse',
            ];
        }

        return $directions;
    }

    /** @return Collection<int, CatalogPart> */
    private function partsByIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return CatalogPart::query()
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->whereKey($ids)
            ->get();
    }
}
