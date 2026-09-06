<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogSerializer;
use App\Catalog\Relations\CatalogPartGraphQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartGraphResolveController extends Controller
{
    public function __invoke(
        string $number,
        Request $request,
        CatalogPartGraphQuery $graph,
        CatalogSerializer $serializer,
    ): JsonResponse {
        $validated = $request->validate([
            'scheme' => ['nullable', 'string', 'max:32'],
            'depth' => ['nullable', 'integer', 'min:0', 'max:5'],
            'max_nodes' => ['nullable', 'integer', 'min:1', 'max:500'],
            'max_edges' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $result = $graph->resolve(
            $number,
            $validated['scheme'] ?? null,
            (int) ($validated['depth'] ?? 2),
            (int) ($validated['max_nodes'] ?? 100),
            (int) ($validated['max_edges'] ?? 250),
        );

        $publicIds = collect($result['nodes'])->mapWithKeys(
            fn (array $node): array => [$node['part']->id => 'prt_'.$node['part']->public_id],
        );

        $nodes = collect($result['nodes'])->map(function (array $node) use ($serializer): array {
            return [
                'part' => $serializer->part($node['part']),
                'depth' => $node['depth'],
                'path_confidence' => $node['path_confidence'],
                'seed' => $node['seed'],
            ];
        })->values();

        $edges = collect($result['edges'])->map(function (array $edge) use ($publicIds): array {
            $relation = $edge['relation'];

            return [
                'id' => 'rel_'.$relation->id,
                'from_part_id' => $publicIds->get($edge['from_part_id']),
                'to_part_id' => $publicIds->get($edge['to_part_id']),
                'relation_type' => $relation->relation_type,
                'directed' => (bool) $relation->is_directed,
                'traversal_direction' => $edge['traversal_direction'],
                'confidence' => $relation->confidence !== null ? (float) $relation->confidence : null,
                'source' => $relation->source ? [
                    'code' => $relation->source->code,
                    'name' => $relation->source->name,
                    'attribution_required' => (bool) $relation->source->attribution_required,
                    'license_name' => $relation->source->license_name,
                ] : null,
            ];
        })->values();

        return response()->json([
            'query' => [
                'number' => $number,
                'scheme' => $validated['scheme'] ?? null,
                'depth' => (int) ($validated['depth'] ?? 2),
            ],
            'data' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            'meta' => [
                'seed_count' => $result['seeds']->count(),
                'node_count' => $nodes->count(),
                'edge_count' => $edges->count(),
                'truncated' => $result['truncated'],
            ],
        ]);
    }
}
