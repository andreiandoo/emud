<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogPartRelation;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartGraphApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_traverses_only_api_redistributable_edges_and_visible_parts(): void
    {
        $publicSource = $this->source('PUBLIC', true);
        $privateSource = $this->source('PRIVATE', false);

        $a = $this->part('MAHLE', 'OC 123', $publicSource);
        $b = $this->part('MANN-FILTER', 'W 68/3', $publicSource);
        $c = $this->part('BOSCH', '0 451 103 336', $publicSource);

        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $b->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $publicSource->id,
            'confidence' => 98,
        ]);
        CatalogPartRelation::query()->create([
            'source_part_id' => $b->id,
            'target_part_id' => $c->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $privateSource->id,
            'confidence' => 99,
        ]);

        $token = $this->apiToken();
        $response = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/'.urlencode('OC 123').'/graph?scheme=MPN&depth=4');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(['prt_'.$a->public_id], $data['seeds']);
        $this->assertCount(2, $data['nodes']);
        $this->assertCount(1, $data['edges']);
        $this->assertSame('prt_'.$a->public_id, $data['edges'][0]['from']);
        $this->assertSame('prt_'.$b->public_id, $data['edges'][0]['to']);
        $this->assertSame('equivalent', $data['edges'][0]['relation_type']);
        $this->assertFalse($data['edges'][0]['directed']);
        $this->assertFalse($data['truncated']);
        $this->assertNotContains('prt_'.$c->public_id, array_column(array_column($data['nodes'], 'part'), 'id'));
    }

    public function test_graph_depth_limits_transitive_expansion_and_reports_best_path_confidence(): void
    {
        $source = $this->source('PUBLIC', true);
        $a = $this->part('A', 'A-1', $source);
        $b = $this->part('B', 'B-1', $source);
        $c = $this->part('C', 'C-1', $source);

        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $b->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $source->id,
            'confidence' => 92,
        ]);
        CatalogPartRelation::query()->create([
            'source_part_id' => $b->id,
            'target_part_id' => $c->id,
            'relation_type' => 'superseded_by',
            'is_directed' => true,
            'catalog_source_id' => $source->id,
            'confidence' => 81,
        ]);

        $token = $this->apiToken();
        $depthOne = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?scheme=MPN&depth=1')
            ->assertOk()
            ->json('data');
        $this->assertCount(2, $depthOne['nodes']);

        $depthTwo = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?scheme=MPN&depth=2')
            ->assertOk()
            ->json('data');
        $this->assertCount(3, $depthTwo['nodes']);
        $this->assertCount(2, $depthTwo['edges']);

        $nodes = collect($depthTwo['nodes'])->keyBy(fn (array $node) => $node['part']['id']);
        $this->assertEquals(100.0, $nodes['prt_'.$a->public_id]['path_confidence']);
        $this->assertEquals(92.0, $nodes['prt_'.$b->public_id]['path_confidence']);
        $this->assertEquals(81.0, $nodes['prt_'.$c->public_id]['path_confidence']);
    }

    public function test_graph_edge_budget_truncates_dense_components_without_exceeding_the_limit(): void
    {
        $source = $this->source('PUBLIC', true);
        $a = $this->part('A', 'A-1', $source);
        $b = $this->part('B', 'B-1', $source);
        $c = $this->part('C', 'C-1', $source);

        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $b->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $source->id,
            'confidence' => 99,
        ]);
        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $c->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $source->id,
            'confidence' => 80,
        ]);

        $data = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson('/api/v1/parts/by-number/A-1/graph?scheme=MPN&depth=1&max_nodes=10&max_edges=1')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['query']['max_edges']);
        $this->assertCount(1, $data['edges']);
        $this->assertTrue($data['truncated']);
        $this->assertSame('prt_'.$b->public_id, $data['edges'][0]['to']);
        $this->assertContains('prt_'.$b->public_id, array_column(array_column($data['nodes'], 'part'), 'id'));
        $this->assertNotContains('prt_'.$c->public_id, array_column(array_column($data['nodes'], 'part'), 'id'));
    }

    public function test_graph_endpoint_validates_bounds_and_requires_an_api_key(): void
    {
        $this->getJson('/api/v1/parts/by-number/A-1/graph')->assertUnauthorized();

        $token = $this->apiToken();
        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?depth=5')
            ->assertUnprocessable();
        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?max_nodes=251')
            ->assertUnprocessable();
        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?max_edges=2001')
            ->assertUnprocessable();
        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/A-1/graph?max_edges=0')
            ->assertUnprocessable();
    }

    private function source(string $code, bool $api): CatalogSource
    {
        return CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $code.' source',
            'code' => $code,
            'source_type' => 'manufacturer',
            'protocol' => 'manual',
            'rights_class' => $api ? 'open_redistributable' : 'internal_reference',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => $api,
            'is_active' => true,
        ]);
    }

    private function part(string $brandName, string $mpn, CatalogSource $source): CatalogPart
    {
        $normalizer = app(IdentifierNormalizer::class);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );
        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $brandName.' part',
            'lifecycle_status' => 'active',
            'quality_score' => 100,
        ]);

        CatalogPartNumber::query()->create([
            'catalog_part_id' => $part->id,
            'brand_id' => $brand->id,
            'scheme' => 'MPN',
            'number_raw' => $mpn,
            'number_normalized' => $normalizer->normalize($mpn),
            'number_compact' => $normalizer->compact($mpn),
            'catalog_source_id' => $source->id,
            'confidence' => 100,
        ]);
        CatalogSourceAssertion::query()->create([
            'catalog_source_id' => $source->id,
            'entity_type' => 'catalog_part',
            'entity_id' => $part->id,
            'field_or_relation' => 'identity',
            'status' => 'published',
            'confidence' => 100,
            'ecommerce_displayable' => true,
            'api_redistributable' => true,
        ]);

        return $part;
    }

    private function apiToken(): string
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Graph test consumer',
            'slug' => 'graph-test-'.Str::lower(Str::random(6)),
            'plan' => 'test',
            'monthly_quota' => 1000,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
        ]);

        return CatalogApiKey::issue($consumer, 'Test')['token'];
    }
}
