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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPartGraphApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_endpoint_returns_public_ids_and_permissioned_relation_graph(): void
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Graph API test',
            'slug' => 'graph-api-test',
            'plan' => 'test',
            'monthly_quota' => 1000,
            'requests_used' => 0,
            'is_active' => true,
        ]);
        $token = CatalogApiKey::issue($consumer)['token'];
        $source = CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Open manufacturer',
            'code' => 'OPEN_GRAPH',
            'source_type' => 'manufacturer',
            'protocol' => 'manual',
            'rights_class' => 'open_redistributable',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'license_name' => 'Test License',
            'is_active' => true,
        ]);
        $a = $this->part('MAHLE', 'OC 123', $source);
        $b = $this->part('MANN-FILTER', 'W 68/3', $source);
        CatalogPartRelation::query()->create([
            'source_part_id' => $a->id,
            'target_part_id' => $b->id,
            'relation_type' => 'equivalent',
            'is_directed' => false,
            'catalog_source_id' => $source->id,
            'confidence' => 94,
        ]);

        $response = $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/resolve/'.rawurlencode('OC 123').'?scheme=MPN&depth=1');

        $response->assertOk()
            ->assertJsonPath('meta.seed_count', 1)
            ->assertJsonPath('meta.node_count', 2)
            ->assertJsonPath('meta.edge_count', 1)
            ->assertJsonPath('data.edges.0.from_part_id', 'prt_'.$a->public_id)
            ->assertJsonPath('data.edges.0.to_part_id', 'prt_'.$b->public_id)
            ->assertJsonPath('data.edges.0.relation_type', 'equivalent')
            ->assertJsonPath('data.edges.0.source.code', 'OPEN_GRAPH');

        $payload = $response->json();
        $this->assertStringNotContainsString('"from_part_id":"prt_'.$a->id.'"', json_encode($payload));
    }

    private function part(string $brandName, string $mpn, CatalogSource $source): CatalogPart
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'is_active' => true],
        );
        $normalizer = app(IdentifierNormalizer::class);
        $part = CatalogPart::query()->create([
            'public_id' => (string) Str::ulid(),
            'brand_id' => $brand->id,
            'mpn_raw' => $mpn,
            'mpn_normalized' => $normalizer->normalize($mpn),
            'name' => $brandName.' '.$mpn,
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

        return $part;
    }
}
