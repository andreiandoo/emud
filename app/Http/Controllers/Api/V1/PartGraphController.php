<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Graph\PartGraphTraversal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartGraphController extends CatalogApiController
{
    public function __invoke(Request $request, PartGraphTraversal $graph, ?string $number = null): JsonResponse
    {
        $validated = $request->validate([
            'number' => [$number === null ? 'required' : 'nullable', 'string', 'max:128'],
            'scheme' => ['nullable', 'string', 'max:32'],
            'depth' => ['nullable', 'integer', 'min:0', 'max:4'],
            'max_nodes' => ['nullable', 'integer', 'min:1', 'max:250'],
            'max_edges' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        // As with the number lookup, the query form is the one that can carry a number
        // containing a slash. See PartByNumberController.
        $number ??= (string) $validated['number'];

        return $this->cached($request, fn (): array => [
            'data' => $graph->byNumber(
                $number,
                $validated['scheme'] ?? null,
                (int) ($validated['depth'] ?? 2),
                (int) ($validated['max_nodes'] ?? 100),
                (int) ($validated['max_edges'] ?? 800),
            ),
        ]);
    }
}
