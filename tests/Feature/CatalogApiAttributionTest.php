<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class CatalogApiAttributionTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_a_response_credits_the_sources_behind_the_rows_it_returned(): void
    {
        $source = $this->apiSource('EEA_TEST');
        $this->apiPart('Mahle', 'OC 123', $source);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/parts/search');

        $response->assertOk();
        $attribution = $response->json('meta.attribution');
        $this->assertCount(1, $attribution);
        $this->assertSame('EEA_TEST', $attribution[0]['code']);
        $this->assertSame('CC BY 4.0', $attribution[0]['license']);
        $this->assertTrue($attribution[0]['attribution_required']);
        $this->assertStringContainsString('CC BY 4.0', $response->json('meta.license_notice'));
    }

    public function test_a_source_that_may_not_be_republished_is_never_named(): void
    {
        $public = $this->apiSource('OPEN_ONE');
        $restricted = $this->apiSource('RESTRICTED_ONE', api: false);
        $part = $this->apiPart('Mahle', 'OC 456', $public);

        // The restricted catalogue also knows this part. Its number must not be published, and
        // neither must the fact that it was consulted.
        $normalizer = app(IdentifierNormalizer::class);
        $part->numbers()->create([
            'brand_id' => $part->brand_id,
            'scheme' => 'OE',
            'number_raw' => 'SECRET-OE-1',
            'number_normalized' => $normalizer->normalize('SECRET-OE-1'),
            'number_compact' => $normalizer->compact('SECRET-OE-1'),
            'catalog_source_id' => $restricted->id,
            'confidence' => 100,
        ]);

        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson('/api/v1/parts/'.$part->public_id);

        $response->assertOk();
        $codes = collect($response->json('meta.attribution'))->pluck('code');
        $this->assertContains('OPEN_ONE', $codes);
        $this->assertNotContains('RESTRICTED_ONE', $codes);
        $this->assertStringNotContainsString('SECRET-OE-1', $response->getContent());
    }

    public function test_a_source_that_asks_for_no_credit_produces_no_notice(): void
    {
        $source = $this->apiSource('PUBLIC_DOMAIN', api: true, attribution: false);
        $this->apiPart('NHTSA', 'PD-1', $source);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/parts/search');

        $response->assertOk();
        $this->assertCount(1, $response->json('meta.attribution'));
        $this->assertNull($response->json('meta.license_notice'));
    }

    public function test_the_sources_endpoint_publishes_licence_terms_for_redistributable_sources_only(): void
    {
        $this->apiSource('OPEN_TWO');
        $this->apiSource('RESTRICTED_TWO', api: false);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/sources');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertContains('OPEN_TWO', $codes);
        $this->assertNotContains('RESTRICTED_TWO', $codes);
        $this->assertSame('CC BY 4.0', $response->json('data.0.license'));
    }

    public function test_a_vehicle_response_credits_the_source_that_published_it(): void
    {
        $source = $this->apiSource('VEHICLE_SOURCE');
        $vehicle = $this->apiVehicle($source);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertOk();
        $this->assertSame('VEHICLE_SOURCE', $response->json('meta.attribution.0.code'));
    }
}
