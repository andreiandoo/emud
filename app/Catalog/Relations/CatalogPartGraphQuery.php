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
        $queue = [];
        $visitedDepth = [];
        $truncated = false;

        foreach ($seeds as $part) {
            $nodes[$part->id] = [
                'part' => $part,
                'depth' => 0,
                'path_confidence' => 100.0,
                'seed' => true,
            ];
            $visitedDepth[$part->id] = 0;
            $queue[] = [$part->id, 0, 100.0];
        }

        while ($queue !== []) {
            [$partId, $currentDepth, $pathConfidence] = array_shift($queue);
            if ($currentDepth >= $depth) {
                continue;
            }

            $relations = $this->relationsForPart($partId);
            foreach ($relations as $relation) {
                if (count($edges) >= $maxEdges) {
                    $truncated = true;
                    break 2;
                }

                $forward = $relation->source_part_id === $partId;
                $nextId = $forward ? $relation->target_part_id : $relation->source_part_id;
                if (! $nextId || $nextId === $partId) {
                    continue;
                }

                $edgeKey = $relation->id.':'.($forward ? 'forward' : 'reverse');
                if (! isset($edges[$edgeKey])) {
                    $edges[$edgeKey] = [
                        'relation' => $relation,
                        'from_part_id' => $partId,
                        'to_part_id' => $nextId,
                        'traversal_direction' => $forward ? 'forward' : 'reverse',
                    ];
                }

                $nextDepth = $currentDepth + 1;
                $edgeConfidence = (float) ($relation->confidence ?? 0);
                $nextConfidence = min($pathConfidence, $edgeConfidence > 0 ? $edgeConfidence : $pathConfidence);

                if (! isset($nodes[$nextId])) {
                    if (count($nodes) >= $maxNodes) {
                        $truncated = true;
                        continue;
                    }

                    $part = CatalogPart::query()
                        ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
                        ->find($nextId);
                    if (! $part) {
                        continue;
                    }

                    $nodes[$nextId] = [
                        'part' => $part,
                        'depth' => $nextDepth,
                        'path_confidence' => $nextConfidence,
                        'seed' => false,
                    ];
                } else {
                    $nodes[$nextId]['path_confidence'] = max((float) $nodes[$nextId]['path_confidence'], $nextConfidence);
                    $nodes[$nextId]['depth'] = min((int) $nodes[$nextId]['depth'], $nextDepth);
                }

                if (! isset($visitedDepth[$nextId]) || $nextDepth < $visitedDepth[$nextId]) {
                    $visitedDepth[$nextId] = $nextDepth;
                    $queue[] = [$nextId, $nextDepth, $nextConfidence];
                }
            }
        }

        return [
            'seeds' => $seeds,
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
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
    private function relationsForPart(int $partId): Collection
    {
        return CatalogPartRelation::query()
            ->with(['source', 'sourcePart.brand', 'targetPart.brand'])
            ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
            ->where(function ($query) use ($partId): void {
                $query->where('source_part_id', $partId)
                    ->orWhere('target_part_id', $partId);
            })
            ->orderByDesc('confidence')
            ->orderBy('id')
            ->limit(1000)
            ->get();
    }
}
