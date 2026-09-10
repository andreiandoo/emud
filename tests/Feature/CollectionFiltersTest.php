<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Enums\SupplierProtocol;
use App\Livewire\Storefront\CollectionPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\VehicleCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The filter rail on a collection page.
 *
 * Facet counts are the part worth pinning: counting a facet with its own filter applied makes
 * every unticked box read zero, so the customer can only ever narrow and never widen. That
 * mistake looks like working software until someone tries to tick a second brand.
 */
class CollectionFiltersTest extends TestCase
{
    use RefreshDatabase;

    private VehicleCollection $collection;

    private Category $suspension;

    private Category $brakes;

    private Brand $icon;

    private Brand $ome;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = VehicleCollection::create([
            'name' => 'Suzuki Jimny', 'slug' => 'suzuki-jimny', 'is_active' => true,
        ]);

        $this->suspension = Category::create([
            'name' => 'Suspensie', 'slug' => 'suspensie', 'full_path' => 'suspensie', 'is_active' => true,
        ]);
        $this->brakes = Category::create([
            'name' => 'Frâne', 'slug' => 'frane', 'full_path' => 'frane', 'is_active' => true,
        ]);

        $this->icon = Brand::create(['name' => 'ICON', 'slug' => 'icon', 'is_active' => true]);
        $this->ome = Brand::create(['name' => 'Old Man Emu', 'slug' => 'old-man-emu', 'is_active' => true]);
    }

    public function test_ticking_a_category_narrows_the_listing(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->assertSee('Amortizor')
            ->assertSee('Plăcuțe')
            ->set('categories', ['suspensie'])
            ->assertSee('Amortizor')
            ->assertDontSee('Plăcuțe');
    }

    /**
     * With the category filter applied to its own facet counts, "Frâne" would read 0 and ticking
     * it would empty the page. It has to count as if the category filter were not there.
     */
    public function test_a_category_facet_still_counts_while_another_category_is_ticked(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        $facets = Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('categories', ['suspensie'])
            ->viewData('categoryFacets');

        $this->assertSame(1, $facets->firstWhere('slug', 'frane')['total']);
        $this->assertSame(1, $facets->firstWhere('slug', 'suspensie')['total']);
    }

    /** A brand facet, by contrast, must respect the category filter — that is what narrows. */
    public function test_a_brand_facet_respects_the_other_filters(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        $facets = Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('categories', ['suspensie'])
            ->viewData('brandFacets');

        $this->assertSame(['ICON'], $facets->pluck('name')->all());
    }

    public function test_the_price_range_filters_on_the_cheapest_active_variant(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('priceMin', '500')
            ->assertSee('Amortizor')
            ->assertDontSee('Plăcuțe')
            ->set('priceMin', '')
            ->set('priceMax', '500')
            ->assertDontSee('Amortizor')
            ->assertSee('Plăcuțe');
    }

    public function test_sorting_by_price_orders_by_the_cheapest_variant(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        $names = Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('sort', 'price-asc')
            ->viewData('products')
            ->pluck('name')
            ->all();

        $this->assertSame(['Plăcuțe', 'Amortizor'], $names);
    }

    /** Backorder is not the same promise as stock, so it must not pass an "in stock" filter. */
    public function test_the_stock_filter_keeps_only_what_a_supplier_has_now(): void
    {
        $available = $this->product('Amortizor', $this->suspension, $this->icon, 800);
        $backordered = $this->product('Plăcuțe', $this->brakes, $this->ome, 200);

        $this->offer($available, StockStatus::InStock);
        $this->offer($backordered, StockStatus::Backorder);

        Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('inStock', true)
            ->assertSee('Amortizor')
            ->assertDontSee('Plăcuțe');
    }

    public function test_a_single_filter_can_be_dropped_from_the_summary_row(): void
    {
        $this->product('Amortizor', $this->suspension, $this->icon, 800);

        Livewire::test(CollectionPage::class, ['slug' => 'suzuki-jimny'])
            ->set('categories', ['suspensie', 'frane'])
            ->set('inStock', true)
            ->call('removeFilter', 'category', 'frane')
            ->assertSet('categories', ['suspensie'])
            ->assertSet('inStock', true)
            ->call('clearFilters')
            ->assertSet('categories', [])
            ->assertSet('inStock', false);
    }

    public function test_the_hero_carries_the_wide_image_and_the_page_is_full_width(): void
    {
        $this->collection->update(['wide_image_path' => 'collections/jimny-wide.jpg']);

        $this->get($this->collection->url())
            ->assertOk()
            ->assertSee('collections/jimny-wide.jpg')
            // The gutter belongs to the sections, not to <main>, or the hero cannot reach the edge.
            ->assertDontSee('<main class="shell py-8">', false);
    }

    private function product(string $name, Category $category, Brand $brand, float $price): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'brand_id' => $brand->id,
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);

        $product->categories()->attach($category->id, ['is_primary' => true]);
        $product->collections()->attach($this->collection->id, ['is_automatic' => true]);
        $product->variants()->create([
            'sku' => 'SKU-'.str($name)->slug()->value(),
            'retail_price' => $price,
            'currency' => 'RON',
            'is_active' => true,
        ]);

        return $product;
    }

    private function offer(Product $product, StockStatus $status): void
    {
        $supplier = Supplier::query()->firstOrCreate(['code' => 'demo'], [
            'name' => 'Demo',
            'protocol' => SupplierProtocol::Csv,
            'default_currency' => 'RON',
            'is_active' => true,
        ]);

        $supplierProduct = $product->supplierProducts()->create([
            'supplier_id' => $supplier->id,
            'external_id' => 'ext-'.$product->id,
            'name' => $product->name,
        ]);

        $supplierProduct->offer()->create([
            'stock_status' => $status->value,
            'stock_quantity' => 5,
            'currency' => 'RON',
            'is_active' => true,
        ]);
    }
}
