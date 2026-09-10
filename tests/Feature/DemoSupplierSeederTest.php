<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierCatalogImporter;
use Database\Seeders\AttributeSeeder;
use Database\Seeders\CategorySeeder;
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

    /**
     * The shop lists only active products, and the importer parks feed-created ones in review.
     * Without the publish step the whole chain reports success and the storefront stays empty.
     */
    public function test_the_seeded_catalogue_reaches_the_storefront(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);
        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $this->assertSame(0, Product::query()->where('status', ProductStatus::Active)->count());

        $this->artisan('suppliers:publish-feed-products '.DemoSupplierSeeder::CODE)->assertSuccessful();

        $published = Product::query()->where('status', ProductStatus::Active)->whereNotNull('published_at')->count();
        $this->assertGreaterThan(0, $published);
    }

    public function test_publishing_leaves_products_from_other_sources_alone(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);
        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $handmade = Product::query()->create([
            'name' => 'Produs adăugat manual',
            'slug' => 'produs-adaugat-manual',
            'status' => ProductStatus::Review,
        ]);

        $this->artisan('suppliers:publish-feed-products '.DemoSupplierSeeder::CODE)->assertSuccessful();

        $this->assertSame(ProductStatus::Review, $handmade->fresh()->status);
    }

    /**
     * The importer used to create a shell: name, brand, MPN, description and nothing else. Every
     * supplier, real or demo, produced products with no category, no weight, no dimensions, no
     * specifications and no compatibility — which is not something you can build a shop on.
     */
    public function test_an_imported_product_carries_the_data_the_feed_supplied(): void
    {
        Storage::fake('local');
        $this->seed(CategorySeeder::class);
        $this->seed(AttributeSeeder::class);
        $this->seed(DemoSupplierSeeder::class);
        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $product = Product::query()
            ->with(['categories', 'attributeValues', 'fitments'])
            ->where('name', 'like', 'Bară față din oțel%')
            ->firstOrFail();

        $this->assertNotNull($product->short_description);
        $this->assertSame(24, $product->warranty_months);
        $this->assertEqualsWithDelta(48.0, (float) $product->weight_kg, 0.001);
        // assertEquals, not assertSame: Postgres stores jsonb with its keys reordered
        // (shortest first), so the same three dimensions come back as width, height,
        // length. What matters is each value under its name, not the order.
        $this->assertEquals(['length' => 186.0, 'width' => 62.0, 'height' => 44.0], array_map('floatval', $product->dimensions_cm));
        $this->assertGreaterThan(0, $product->categories->count());
        $this->assertGreaterThan(0, $product->attributeValues->count());
        $this->assertGreaterThan(0, $product->fitments->count());
        $this->assertFalse($product->is_universal);
    }

    /** A part that fits nothing in particular has to be offered to everyone, not to no one. */
    public function test_a_part_without_fitments_is_marked_universal(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);

        $importer = app(SupplierCatalogImporter::class);
        $supplier = Supplier::query()->where('code', DemoSupplierSeeder::CODE)->firstOrFail();
        $importer->import($supplier, new SupplierRecord(
            externalId: 'NO-FIT-1',
            name: 'Trusă de scule universală',
            brand: 'Kestrel 4x4',
            manufacturerPartNumber: 'KST-TOOL-1',
        ), 'catalog');

        $product = Product::query()->where('name', 'Trusă de scule universală')->firstOrFail();

        $this->assertTrue($product->is_universal);
    }

    /**
     * The offer comparison only means anything with more than one supplier, and the two demo
     * feeds share brand and MPN precisely so they land as two offers on one product rather than
     * as two products.
     */
    public function test_both_demo_suppliers_land_on_the_same_products(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);

        foreach (['DEMO_OFFROAD', 'DEMO_PARTS_RO'] as $code) {
            $this->artisan("suppliers:sync {$code} --mode=catalog")->assertSuccessful();
        }

        $product = Product::query()->withCount('supplierProducts')->where('name', 'like', 'Bară față din oțel%')->firstOrFail();

        $this->assertSame(2, $product->supplier_products_count);

        // Both supplier products point at one canonical part, which is what makes them offers
        // on the same thing rather than two separate parts that merely look alike.
        $this->assertCount(1, SupplierProduct::query()
            ->where('product_id', $product->id)
            ->pluck('catalog_part_id')
            ->filter()
            ->unique());
    }

    /** Freight and the dropship fee are what make the cheaper quote the dearer part. */
    public function test_the_feed_carries_the_costs_that_decide_which_supplier_is_cheaper(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);
        $this->artisan('suppliers:sync '.DemoSupplierSeeder::CODE.' --mode=catalog')->assertSuccessful();

        $offer = SupplierOffer::query()->firstOrFail();

        $this->assertNotNull($offer->shipping_cost_estimate);
        $this->assertNotNull($offer->dropship_fee);
        $this->assertNotNull($offer->dispatch_days_min);
    }

    public function test_the_demo_supplier_passes_its_own_onboarding_check(): void
    {
        Storage::fake('local');
        $this->seed(DemoSupplierSeeder::class);

        $this->artisan('suppliers:onboarding-check '.DemoSupplierSeeder::CODE)->assertSuccessful();
    }
}
