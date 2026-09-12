<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class CatalogApiCacheTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_an_identical_read_is_served_from_cache(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 300', $source);
        $token = $this->apiToken();

        $first = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search');
        $first->assertOk()->assertHeader('X-Catalog-Cache', 'miss');

        $second = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search');
        $second->assertOk()->assertHeader('X-Catalog-Cache', 'hit');
        $this->assertSame($first->json('data'), $second->json('data'));
    }

    public function test_a_different_page_is_a_different_entry(): void
    {
        $source = $this->apiSource();
        foreach (range(1, 4) as $i) {
            $this->apiPart('Brand'.$i, 'CN-'.$i, $source);
        }
        $token = $this->apiToken();

        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=2&page=1')
            ->assertHeader('X-Catalog-Cache', 'miss');
        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=2&page=2')
            ->assertHeader('X-Catalog-Cache', 'miss');
        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=2&page=1')
            ->assertHeader('X-Catalog-Cache', 'hit');
    }

    public function test_withdrawing_a_source_retires_the_cached_page_that_published_it(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 400', $source);
        $token = $this->apiToken();

        $this->assertCount(1, $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search')->json('data'));

        // The operator revokes redistribution. A cache that outlived that decision would keep
        // republishing data whose permission has just been withdrawn — the one staleness this
        // API cannot afford.
        $source->update(['allow_api_redistribution' => false]);

        $after = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search');
        $after->assertOk()->assertHeader('X-Catalog-Cache', 'miss');
        $this->assertSame([], $after->json('data'));
    }

    public function test_a_new_part_retires_the_cached_listing(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 500', $source);
        $token = $this->apiToken();

        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search');
        $this->apiPart('Mann', 'W 500', $source);

        $after = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search');
        $after->assertHeader('X-Catalog-Cache', 'miss');
        $this->assertCount(2, $after->json('data'));
    }

    public function test_a_cache_hit_still_reports_the_callers_own_quota(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 600', $source);

        $first = $this->withHeader('X-API-Key', $this->apiToken(quota: 10))->getJson('/api/v1/parts/search');
        $first->assertOk();
        $this->assertSame('9', $first->headers->get('X-Catalog-Monthly-Remaining'));

        // A second consumer reads the same cached bytes but must see its own counter, not the
        // one that happened to warm the entry.
        $second = $this->withHeader('X-API-Key', $this->apiToken(quota: 500))->getJson('/api/v1/parts/search');
        $second->assertOk()->assertHeader('X-Catalog-Cache', 'hit');
        $this->assertSame('499', $second->headers->get('X-Catalog-Monthly-Remaining'));
        $this->assertSame('500', $second->headers->get('X-Catalog-Monthly-Quota'));
    }

    public function test_caching_can_be_switched_off(): void
    {
        config(['catalog_api.cache.enabled' => false]);
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 700', $source);
        $token = $this->apiToken();

        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search')->assertHeader('X-Catalog-Cache', 'miss');
        $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search')->assertHeader('X-Catalog-Cache', 'miss');
    }
}
