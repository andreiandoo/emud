<?php

namespace Tests\Feature;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Catalog\Vehicles\Vin\VpicDecodedVehicleMatcher;
use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use App\Models\SupplierProduct;
use App\Models\VehicleConfiguration;
use Database\Seeders\AutomotiveCatalogDemoFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPublicationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_fixture_normalizes_supplier_mapping_statuses(): void
    {
        $this->seed(AutomotiveCatalogDemoFixtureSeeder::class);

        $this->assertSame(4, SupplierProduct::query()
            ->where('external_id', 'like', 'SUP-DEMO-00%')
            ->where('catalog_mapping_status', 'mapped_auto')
            ->count());

        $this->assertSame(0, SupplierProduct::query()
            ->where('external_id', 'like', 'SUP-DEMO-%')
            ->where('catalog_mapping_status', 'auto_mapped')
            ->count());
    }

    public function test_by_number_requires_both_number_source_rights_and_canonical_part_publication(): void
    {
        $this->seed(AutomotiveCatalogDemoFixtureSeeder::class);
        $token = $this->apiToken();

        $restricted = CatalogPart::query()->where('mpn_raw', 'DEMO-PRIVATE-900')->firstOrFail();
        $publicSource = CatalogSource::query()->where('code', 'DEMO_PUBLIC')->firstOrFail();
        $normalizer = app(IdentifierNormalizer::class);
        $number = 'DEMO-PUBLIC-ALIAS-PRIVATE-900';

        CatalogPartNumber::query()->create([
            'catalog_part_id' => $restricted->id,
            'brand_id' => $restricted->brand_id,
            'scheme' => 'IAM',
            'namespace' => 'publication-boundary-test',
            'number_raw' => $number,
            'number_normalized' => $normalizer->normalize($number),
            'number_compact' => $normalizer->compact($number),
            'catalog_source_id' => $publicSource->id,
            'confidence' => 100,
        ]);

        $this->withHeader('X-API-Key', $token)
            ->getJson('/api/v1/parts/by-number/'.$number.'?scheme=IAM')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_public_vin_matching_cannot_return_non_publishable_canonical_vehicle(): void
    {
        $this->seed(AutomotiveCatalogDemoFixtureSeeder::class);

        $vehicle = VehicleConfiguration::query()
            ->where('commercial_name', 'Duster 1.5 4x4 DEMO')
            ->firstOrFail();

        $decoded = [
            'Make' => 'Dacia',
            'Model' => 'Duster',
            'ModelYear' => 2019,
            'VehicleType' => 'MULTIPURPOSE PASSENGER VEHICLE (MPV)',
        ];

        $matcher = app(VpicDecodedVehicleMatcher::class);
        $internal = $matcher->match($decoded, false);

        $this->assertSame('high_confidence', $internal->status);
        $this->assertSame($vehicle->id, $internal->vehicleConfigurationId);

        CatalogSourceAssertion::query()
            ->where('entity_type', 'vehicle_configuration')
            ->where('entity_id', $vehicle->id)
            ->update(['api_redistributable' => false]);

        $public = $matcher->match($decoded, true);

        $this->assertSame('basic_only', $public->status);
        $this->assertNull($public->vehicleConfigurationId);
        $this->assertSame([], $public->candidates);
        $this->assertContains('canonical vehicle mapping', $public->missing);
    }

    private function apiToken(): string
    {
        $consumer = CatalogApiConsumer::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Publication boundary test consumer',
            'slug' => 'publication-boundary-'.Str::lower(Str::random(8)),
            'plan' => 'test',
            'monthly_quota' => 1000,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
        ]);

        return CatalogApiKey::issue($consumer, 'Test')['token'];
    }
}
