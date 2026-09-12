<?php

namespace Tests\Feature;

use App\Models\CatalogChangeEvent;
use App\Models\CatalogSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class CatalogApiChangeFeedTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_the_cursor_walks_a_batch_that_shares_one_timestamp_without_skipping_or_repeating(): void
    {
        // An import writes its events together, so a whole batch carries one `occurred_at`.
        // Resuming from a timestamp alone either loses the rest of the batch or replays it.
        $at = now()->subHour();
        foreach (range(1, 5) as $i) {
            $this->event('catalog_part', $i, $at);
        }

        $token = $this->apiToken();
        $seen = [];
        $cursor = null;

        for ($page = 0; $page < 5; $page++) {
            $url = '/api/v1/changes?per_page=2'.($cursor ? '&cursor='.urlencode($cursor) : '');
            $response = $this->withHeader('X-API-Key', $token)->getJson($url);
            $response->assertOk();

            $ids = collect($response->json('data'))->pluck('entity_id')->all();
            $seen = [...$seen, ...$ids];
            $cursor = $response->json('meta.next_cursor');

            if (! $response->json('meta.has_more')) {
                break;
            }
        }

        $this->assertSame([1, 2, 3, 4, 5], $seen);
        $this->assertCount(5, array_unique($seen));
    }

    public function test_a_drained_feed_returns_an_empty_page_and_keeps_the_cursor_usable(): void
    {
        $this->event('catalog_part', 1, now()->subHour());
        $token = $this->apiToken();

        $first = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/changes?per_page=10');
        $first->assertOk();
        $this->assertCount(1, $first->json('data'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $empty = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/changes?cursor='.urlencode($cursor));
        $empty->assertOk();
        $this->assertSame([], $empty->json('data'));
        $this->assertFalse($empty->json('meta.has_more'));
        // The cursor survives an empty poll, so a consumer can keep the same one and come back.
        $this->assertSame($cursor, $empty->json('meta.next_cursor'));

        // An event arriving after that poll is picked up by the very same cursor.
        $this->event('catalog_part', 2, now());
        $resumed = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/changes?cursor='.urlencode($cursor));
        $resumed->assertOk();
        $this->assertSame([2], collect($resumed->json('data'))->pluck('entity_id')->all());
    }

    public function test_events_that_may_not_be_republished_never_enter_the_feed(): void
    {
        $this->event('catalog_part', 1, now()->subHour());
        $this->event('catalog_part', 2, now()->subHour(), redistributable: false);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/changes?per_page=50');

        $response->assertOk();
        $this->assertSame([1], collect($response->json('data'))->pluck('entity_id')->all());
    }

    public function test_revoking_a_sources_rights_removes_its_events_from_the_feed(): void
    {
        $source = $this->apiSource();
        $this->event('catalog_part', 1, now()->subHour(), source: $source);
        $token = $this->apiToken();

        $this->assertCount(1, $this->withHeader('X-API-Key', $token)->getJson('/api/v1/changes')->json('data'));

        // The event row still carries `api_redistributable = true` — that was a snapshot of the
        // source's rights when it was written, and nothing rewrites it. The feed has to honour
        // the revocation from the source itself.
        $source->update(['allow_api_redistribution' => false]);

        $this->assertCount(0, $this->withHeader('X-API-Key', $token)->getJson('/api/v1/changes')->json('data'));
    }

    public function test_a_cursor_this_endpoint_did_not_issue_is_rejected_rather_than_ignored(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson('/api/v1/changes?cursor=not-a-cursor');

        $response->assertStatus(422);
        $this->assertSame('CURSOR_INVALID', $response->json('error.code'));
    }

    private function event(
        string $entityType,
        int $entityId,
        $at,
        bool $redistributable = true,
        ?CatalogSource $source = null,
    ): CatalogChangeEvent {
        return CatalogChangeEvent::query()->create([
            'public_id' => (string) Str::ulid(),
            'event_type' => 'upserted',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'catalog_source_id' => $source?->id,
            'payload' => ['entity_id' => $entityId],
            'api_redistributable' => $redistributable,
            'occurred_at' => $at,
        ]);
    }
}
