<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class CatalogApiBatchTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_many_numbers_are_resolved_in_one_call(): void
    {
        $source = $this->apiSource();
        $this->apiPart('Mahle', 'OC 123', $source);
        $this->apiPart('Mann', 'W 68/3', $source);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->postJson('/api/v1/parts/by-number/batch', [
            'numbers' => ['OC 123', 'W 68/3', 'NOT-A-REAL-NUMBER'],
        ]);

        $response->assertOk();
        $this->assertSame(3, $response->json('meta.requested'));
        $this->assertSame(2, $response->json('meta.matched'));

        $rows = collect($response->json('data'))->keyBy('number');
        $this->assertCount(1, $rows['OC 123']['matches']);
        $this->assertSame('OC 123', $rows['OC 123']['matches'][0]['mpn']);
        // A number nobody stocks is reported as an empty result, not omitted: a caller matching
        // its own catalogue needs to know which of its rows failed, not just which succeeded.
        $this->assertSame([], $rows['NOT-A-REAL-NUMBER']['matches']);
    }

    public function test_the_batch_cost_does_not_grow_with_the_number_of_items(): void
    {
        $source = $this->apiSource();
        foreach (range(1, 20) as $i) {
            $this->apiPart('Brand'.$i, 'BN-'.$i, $source);
        }

        $token = $this->apiToken();
        $numbers = collect(range(1, 20))->map(fn ($i) => 'BN-'.$i)->all();

        DB::enableQueryLog();
        $response = $this->withHeader('X-API-Key', $token)
            ->postJson('/api/v1/parts/by-number/batch', ['numbers' => $numbers]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertSame(20, $response->json('meta.matched'));
        // Auth, metering, the two lookups and the attribution roll-up — nowhere near one query
        // per number, which is the whole reason the endpoint exists.
        $this->assertLessThan(20, $queries);
    }

    public function test_a_batch_over_the_ceiling_is_refused_rather_than_truncated_silently(): void
    {
        config(['catalog_api.batch.max_items' => 5]);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->postJson('/api/v1/parts/by-number/batch', [
            'numbers' => ['a', 'b', 'c', 'd', 'e', 'f'],
        ]);

        $response->assertStatus(422);
        $this->assertSame('VALIDATION_FAILED', $response->json('error.code'));
        $this->assertArrayHasKey('numbers', $response->json('error.details'));
    }

    public function test_an_empty_batch_is_refused(): void
    {
        $this->withHeader('X-API-Key', $this->apiToken())
            ->postJson('/api/v1/parts/by-number/batch', ['numbers' => []])
            ->assertStatus(422);
    }

    public function test_a_scheme_narrows_a_batch_to_one_identifier_namespace(): void
    {
        $source = $this->apiSource();
        $part = $this->apiPart('Mahle', 'OC 200', $source);
        $normalizer = app(IdentifierNormalizer::class);
        $part->numbers()->create([
            'brand_id' => $part->brand_id,
            'scheme' => 'OE',
            'number_raw' => 'LR000001',
            'number_normalized' => $normalizer->normalize('LR000001'),
            'number_compact' => $normalizer->compact('LR000001'),
            'catalog_source_id' => $source->id,
            'confidence' => 100,
        ]);

        $token = $this->apiToken();

        $asOe = $this->withHeader('X-API-Key', $token)->postJson('/api/v1/parts/by-number/batch', [
            'numbers' => ['LR000001', 'OC 200'],
            'scheme' => 'OE',
        ]);
        $asOe->assertOk();
        $rows = collect($asOe->json('data'))->keyBy('number');
        $this->assertCount(1, $rows['LR000001']['matches']);
        $this->assertSame([], $rows['OC 200']['matches']);
    }

    public function test_one_malformed_vin_does_not_cost_the_rest_of_the_batch(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiToken())->postJson('/api/v1/vin/batch', [
            'vins' => ['SALCA2AN4EH123456', 'TOO-SHORT'],
        ]);

        $response->assertOk();
        $rows = collect($response->json('data'))->keyBy('vin');
        $this->assertSame('invalid', $rows['TOO-SHORT']['resolution']);
        // The valid VIN is still answered — as `unsupported` here, because no vPIC source is
        // enabled in a test database, which is itself the honest answer rather than an error.
        $this->assertArrayHasKey('SALCA2AN4EH123456', $rows);
        $this->assertSame(2, $response->json('meta.requested'));
    }

    public function test_a_duplicate_vin_is_decoded_once(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiToken())->postJson('/api/v1/vin/batch', [
            'vins' => ['SALCA2AN4EH123456', 'salca2an4eh123456'],
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.requested'));
        $this->assertCount(1, $response->json('data'));
    }
}
