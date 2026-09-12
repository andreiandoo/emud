<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\ApiPagination;
use App\Models\CatalogChangeEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The feed a consumer mirrors the catalogue from.
 *
 * Paged by cursor rather than by `since` alone: events are written in batches during an import,
 * so a page boundary routinely falls inside a group sharing one `occurred_at`. Resuming from the
 * last timestamp would then either skip the rest of that group or replay it forever. The cursor
 * carries the row id as the tiebreaker, so a resume lands exactly where the last page stopped.
 */
class ChangesController extends CatalogApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $paging = ApiPagination::fromRequest($request, default: 100);
        $cursor = self::decodeCursor((string) $request->query('cursor', ''));

        if ($request->filled('cursor') && $cursor === null) {
            return response()->json([
                'error' => ['code' => 'CURSOR_INVALID', 'message' => 'The cursor is not a cursor this endpoint issued.'],
            ], 422);
        }

        $query = CatalogChangeEvent::query()
            ->where('api_redistributable', true)
            // The flag on the row is a snapshot of the source's rights when the event was
            // written. A source whose rights were revoked since must drop out of the feed too,
            // so the live flag is checked wherever the event records which source it came from.
            ->where(fn ($q) => $q->whereNull('catalog_source_id')
                ->orWhereHas('source', fn ($source) => $source->where('allow_api_redistribution', true)))
            ->orderBy('occurred_at')
            ->orderBy('id');

        if ($cursor !== null) {
            [$occurredAt, $id] = $cursor;
            $query->where(fn ($q) => $q
                ->where('occurred_at', '>', $occurredAt)
                ->orWhere(fn ($tie) => $tie->where('occurred_at', $occurredAt)->where('id', '>', $id)));
        } else {
            $query->where('occurred_at', '>', $request->date('since') ?? now()->subDay());
        }

        // A change feed is walked forward, never jumped into, and counting a table that grows with
        // every import would cost more than the page itself.
        $rows = $query->limit($paging->perPage + 1)->get();
        $hasMore = $rows->count() > $paging->perPage;
        $events = $rows->take($paging->perPage);
        $last = $events->last();

        return response()->json([
            'data' => $events->map(fn (CatalogChangeEvent $event) => [
                'id' => $event->public_id,
                'type' => $event->event_type,
                'entity_type' => $event->entity_type,
                'entity_id' => $event->entity_id,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values(),
            'meta' => array_filter([
                'per_page' => $paging->perPage,
                'limit' => $paging->perPage,
                'has_more' => $hasMore,
                // Always issued, so a caller that drains the feed can store one cursor and come
                // back later to an empty page rather than having to fall back to `since`.
                'next_cursor' => $last ? self::encodeCursor($last) : $request->query('cursor'),
            ], fn ($value) => $value !== null),
        ]);
    }

    private static function encodeCursor(CatalogChangeEvent $event): string
    {
        return base64_encode($event->occurred_at?->toIso8601String().'|'.$event->id);
    }

    /**
     * The timestamp comes back as a Carbon rather than the string it was encoded as: a bound
     * DateTimeInterface is rendered in the connection's own date format, where an ISO-8601
     * string would be compared as text against whatever format the driver stores.
     *
     * @return array{0: CarbonInterface, 1: int}|null
     */
    private static function decodeCursor(string $cursor): ?array
    {
        if ($cursor === '') {
            return null;
        }

        $decoded = base64_decode($cursor, true);
        if ($decoded === false || ! str_contains($decoded, '|')) {
            return null;
        }

        [$occurredAt, $id] = explode('|', $decoded, 2);
        if ($occurredAt === '' || ! ctype_digit($id)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($occurredAt);
        } catch (Throwable) {
            return null;
        }

        return [$parsed, (int) $id];
    }
}
