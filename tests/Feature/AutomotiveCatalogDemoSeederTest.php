<?php

namespace Tests\Feature;

use App\Catalog\Graph\PartGraphTraversal;
use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use App\Models\CatalogPart;
use App\Models\CatalogSource;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\SupplierProduct;
use App\Models\VehicleConfiguration;
use Database\Seeders\AutomotiveCatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomotiveCatalogDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_populates_all_operational_surfaces(): void
    {
        $this->seed(AutomotiveCatalogDemoSeeder::class);

        $first = $this->demoCounts();
        $this->assertSame(4, $first['sources']);
        $this->assertSame(4, $first['vehicles']);
        $this->assertSame(14, $first['parts']);
        $this->assertSame(5, $first['supplier_products']);
        $this->assertSame(1, $first['pending_relations']);

        $this->seed(AutomotiveCatalogDemoSeeder::class);

        $this->assertSame($first, $this->demoCounts());
        $this->assertDatabaseHas('supplier_products', [
            'external_id' => 'SUP-DEMO-UNMAPPED',
            'catalog_mapping_status' => 'unmapped',
            'catalog_part_id' => null,
        ]);
        $this->assertDatabaseHas('catalog_unresolved_part_relations', [
            'target_number_raw' => 'DEMO-MISSING-999',
            'status' => 'pending',
        ]);
    }

    public function test_demo_graph_exposes_public_supersessions_but_not_restricted_bridge(): void
    {
        $this->seed(AutomotiveCatalogDemoSeeder::class);

        $result = app(PartGraphTraversal::class)->byNumber('DEMO-FLT-100', 'MPN', depth: 3, maxNodes: 50, maxEdges: 50);
        $nodes = collect($result['nodes'])->keyBy(fn (array $node) => $node['part']['mpn']);

        $this->assertTrue($nodes->has('DEMO-FLT-100'));
        $this->assertTrue($nodes->has('DEMO-FLT-110'));
        $this->assertTrue($nodes->has('DEMO-FLT-120'));
        $this->assertTrue($nodes->has('DEMO-FLT-130'));
        $this->assertFalse($nodes->has('DEMO-PRIVATE-900'));
        $this->assertEquals(100, $nodes['DEMO-FLT-100']['path_confidence']);
        $this->assertEquals(97, $nodes['DEMO-FLT-110']['path_confidence']);
        $this->assertEquals(92, $nodes['DEMO-FLT-120']['path_confidence']);
        $this->assertEquals(84, $nodes['DEMO-FLT-130']['path_confidence']);
    }

    public function test_vehicle_parts_and_compatibility_enforce_entity_publication_scope(): void
    {
        $this->seed(AutomotiveCatalogDemoSeeder::class);
        $token = $this->apiToken();
        $duster = VehicleConfiguration::query()->where('commercial_name', 'Duster 1.5 4x4 DEMO')->firstOrFail();
        $restricted = CatalogPart::query()->where('mpn_raw', 'DEMO-PRIVATE-900')->firstOrFail();
        $commerceOnly = CatalogPart::query()->where('mpn_raw', 'DEMO-ECO-800')->firstOrFail();

        $parts = $this->withHeader('X-API-Key', $token)
            ->getJson("/api/v1/vehicles/{$duster->id}/parts?limit=100")
            ->assertOk()
            ->json('data');
        $ids = array_column($parts, 'id');

        $this->assertNotContains('prt_'.$restricted->public_id, $ids);
        $this->assertNotContains('prt_'.$commerceOnly->public_id, $ids);
        $this->assertNotEmpty($ids);

        $this->withHeader('X-API-Key', $token)
            ->postJson('/api/v1/compatibility/check', [
                'vehicle_id' => $duster->id,
                'part_id' => 'prt_'.$restricted->public_id,
            ])
            ->assertNotFound();
    }

    public function test_conditional_demo_fitment_returns_constraints(): void
    {
        $this->seed(AutomotiveCatalogDemoSeeder::class);
        $token = $this->apiToken();
        $hilux = VehicleConfiguration::query()->where('commercial_name', 'Hilux 2.8 4WD DEMO')->firstOrFail();
        $liftKit = CatalogPart::query()->where('mpn_raw', 'DEMO-LFT-410')->firstOrFail();

        $data = $this->withHeader('X-API-Key', $token)
            ->postJson('/api/v1/compatibility/check', [
                'vehicle_id' => $hilux->id,
                'part_id' => 'prt_'.$liftKit->public_id,
            ])
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['compatible']);
        $this->assertSame('conditional', $data['status']);
        $this->assertSame('engine_code', $data['constraints'][0]['type']);
        $this->assertSame(['engine_code' => 'DEMO-H28'], $data['constraints'][0]['value']);
    }

    /** @return array{sources:int,vehicles:int,parts:int,supplier_products:int,pending_relations:int} */
    private function demoCounts(): array
    {
        return [
            'sources' => CatalogSource::query()->where('code', 'like', 'DEMO_%')->count(),
            'vehicles' => VehicleConfiguration::query()->where('commercial_name', 'like', '%DEMO%')->count(),
            'parts' => CatalogPart::query()->where('mpn_raw', 'like', 'DEMO-%')->count(),
            'supplier_products' => SupplierProduct::query()->where('external_id', 'like', 'SUP-DEMO-%')->count(),
            'pending_relations' => CatalogUnresolvedPartRelation::query()->where('target_number_raw', 'like', 'DEMO-%')->where('status', 'pending')->count(),
        ];
    }

    private function apiToken(): string
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'DEMO seeder test consumer',
            'slug' => 'demo-seeder-test-'.Str::lower(Str::random(6)),
            'plan' => 'test',
            'monthly_quota' => 1000,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
        ]);

        return CatalogApiKey::issue($consumer, 'Test')['token'];
    }
}
