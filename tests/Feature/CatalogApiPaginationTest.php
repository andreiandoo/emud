<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class CatalogApiPaginationTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_a_list_endpoint_pages_instead_of_capping_at_one_window(): void
    {
        $source = $this->apiSource();
        for ($i = 1; $i <= 7; $i++) {
            $this->apiPart('Brand'.$i, 'PN-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), $source);
        }

        $token = $this->apiToken();

        $first = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=3&page=1');
        $first->assertOk();
        $this->assertCount(3, $first->json('data'));
        $this->assertSame(7, $first->json('meta.total'));
        $this->assertSame(3, $first->json('meta.total_pages'));
        $this->assertTrue($first->json('meta.has_more'));

        $last = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=3&page=3');
        $last->assertOk();
        $this->assertCount(1, $last->json('data'));
        $this->assertFalse($last->json('meta.has_more'));

        // The whole set is reachable, and no part is served on two pages.
        $ids = collect([1, 2, 3])
            ->flatMap(fn (int $page) => $this->withHeader('X-API-Key', $token)
                ->getJson("/api/v1/parts/search?per_page=3&page={$page}")->json('data'))
            ->pluck('id');

        $this->assertCount(7, $ids);
        $this->assertCount(7, $ids->unique());
    }

    public function test_per_page_is_capped_and_the_legacy_limit_parameter_still_works(): void
    {
        $source = $this->apiSource();
        for ($i = 1; $i <= 3; $i++) {
            $this->apiPart('Capped'.$i, 'CAP-'.$i, $source);
        }

        $token = $this->apiToken();

        $capped = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?per_page=5000');
        $capped->assertOk();
        $this->assertSame(100, $capped->json('meta.per_page'));

        $legacy = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/parts/search?limit=2');
        $legacy->assertOk();
        $this->assertCount(2, $legacy->json('data'));
        $this->assertSame(2, $legacy->json('meta.per_page'));
        $this->assertSame(2, $legacy->json('meta.limit'));
    }

    public function test_skipping_the_total_avoids_the_count_but_still_reports_whether_more_exists(): void
    {
        $source = $this->apiSource();
        for ($i = 1; $i <= 4; $i++) {
            $this->apiPart('NoTotal'.$i, 'NT-'.$i, $source);
        }

        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson('/api/v1/parts/search?per_page=2&with_total=0');

        $response->assertOk();
        $this->assertNull($response->json('meta.total'));
        $this->assertTrue($response->json('meta.has_more'));
    }

    public function test_a_part_fitted_twice_to_one_vehicle_is_paged_once_not_repeated(): void
    {
        $source = $this->apiSource();
        $vehicle = $this->apiVehicle($source);

        $pads = $this->apiPart('Brembo', 'P 44 052', $source);
        // Two fitment rows for one part — a left and a right — which is what made the first
        // implementation return short pages with the same part on each of them.
        $this->apiFitment($pads, $vehicle, $source, 'front_left');
        $this->apiFitment($pads, $vehicle, $source, 'front_right');
        $this->apiFitment($this->apiPart('Mahle', 'LX 1234', $source), $vehicle, $source);

        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson("/api/v1/vehicles/{$vehicle->id}/parts?per_page=10");

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertCount(2, $response->json('data'));
        $this->assertCount(2, collect($response->json('data'))->pluck('id')->unique());
    }

    public function test_one_number_recorded_by_two_sources_resolves_to_one_part(): void
    {
        $source = $this->apiSource();
        $second = $this->apiSource('PUBLIC_TWO');
        $part = $this->apiPart('Mann', 'W 68/3', $source);

        // The same number asserted again by another source that may also be republished.
        $part->numbers()->create([
            'brand_id' => $part->brand_id,
            'scheme' => 'MPN',
            'number_raw' => 'W 68/3',
            'number_normalized' => app(IdentifierNormalizer::class)->normalize('W 68/3'),
            'number_compact' => app(IdentifierNormalizer::class)->compact('W 68/3'),
            'catalog_source_id' => $second->id,
            'confidence' => 90,
        ]);

        // A number with a slash in it cannot be addressed through the path at all, so the query
        // form is the one this has to be looked up with.
        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson('/api/v1/parts/lookup?number='.urlencode('W 68/3'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_a_number_containing_a_slash_is_reachable_only_through_the_query_form(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mann', 'HU 718/5 x', $source);
        $token = $this->apiToken();

        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/'.urlencode('HU 718/5 x'))
            ->assertNotFound();

        $found = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/lookup?number='.urlencode('HU 718/5 x'));

        $found->assertOk();
        $this->assertCount(1, $found->json('data'));
        $this->assertSame('HU 718/5 x', $found->json('data.0.mpn'));
    }

    public function test_the_lookup_form_says_what_is_missing_when_no_number_is_given(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/parts/lookup');

        $response->assertStatus(422);
        $this->assertSame('NUMBER_REQUIRED', $response->json('error.code'));
    }
}
