<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Graph\PartGraphTraversal;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartGraphController extends Controller
{
    public function __invoke(string $number, Request $request, PartGraphTraversal $graph): JsonResponse
    {
        $validated = $request->validate([
            'scheme' => ['nullable', 'string', 'max:32'],
            'depth' => ['nullable', 'integer', 'min:0', 'max:4'],
            'max_nodes' => ['nullable', 'integer', 'min:1', 'max:250'],
        ]);

        return response()->json([
            'data' => $graph->byNumber(
                $number,
                $validated['scheme'] ?? null,
                (int) ($validated['depth'] ?? 2),
                (int) ($validated['max_nodes'] ?? 100),
            ),
        ]);
    }
}
