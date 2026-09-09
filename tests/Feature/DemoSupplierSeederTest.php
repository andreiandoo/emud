<?php

namespace Tests\Feature;

use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Database\Seeders\DemoSupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSupplierSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The point of seeding a file and a supplier rather than SupplierProduct rows is that the
     * genuine import path runs: parser, field mapping, delimiters, offers, then promotion into
     * canonical parts. This asserts the whole chain, because anything that breaks in it would
     * have broken on a real feed too.
     */
    public function test_the_demo_feed_imports_into_offers_and_canonical_parts(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);

        $this->assertTrue(Storage::disk('local')->exists(DemoSupplierSeeder::FEED_PATH));

        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $supplier = Supplier::query()->where('code', DemoSupplierSeeder::CODE)->firstOrFail();
        $products = SupplierProduct::query()->where('supplier_id', $supplier->id)->get();

        $this->assertGreaterThan(0, $products->count());
        $this->assertSame($products->count(), SupplierOffer::query()->whereIn('supplier_product_id', $products->modelKeys())->count());

        // Every row carries brand and MPN, so none should stall before promotion.
        $this->assertSame(
            [],
            $products->pluck('technical_promotion_status')->unique()->diff(['promoted'])->values()->all(),
        );

        $this->assertGreaterThan(0, CatalogPart::query()->count());
        $this->assertGreaterThan(0, CatalogFitment::query()->count());
        $this->assertSame(0, $products->whereNull('catalog_part_id')->count());
    }

    /**
     * Fitments resolve by configuration id. A feed written against vehicles that are not in this
     * database would import cleanly and then show up under no vehicle at all, which is the one
     * failure that makes the demo supplier useless for building the shop.
     */
    public function test_seeded_parts_are_reachable_from_a_vehicle(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);
        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $fitment = CatalogFitment::query()->with('configuration')->first();

        $this->assertNotNull($fitment);
        $this->assertNotNull($fitment->configuration);
    }

    public function test_the_demo_supplier_passes_its_own_onboarding_check(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);

        $this->artisan('suppliers:onboarding-check '.DemoSupplierSeeder::CODE)->assertSuccessful();
    }
}
