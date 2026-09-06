<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CatalogChangeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $since = $request->date('since') ?? now()->subDay();
        $limit = min(1000, max(1, (int) $request->query('limit', 100)));

        $events = CatalogChangeEvent::query()
            ->where('api_redistributable', true)
            ->where('occurred_at', '>', $since)
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->public_id,
                'type' => $event->event_type,
                'entity_type' => $event->entity_type,
                'entity_id' => $event->entity_id,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $events, 'meta' => ['limit' => $limit]]);
    }
}
