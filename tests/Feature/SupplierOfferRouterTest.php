<?php

namespace Tests\Feature;

use App\Commerce\SupplierOfferRouter;
use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Storefront\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOfferRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['emud.suppliers.routing' => [
            'backorder_penalty_percent' => 8,
            'low_stock_penalty_percent' => 2,
            'dispatch_day_penalty_percent' => 0.5,
            'unknown_dispatch_days' => 5,
        ]]);
    }

    public function test_the_offer_that_lands_cheapest_wins_not_the_one_quoted_cheapest(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('IEFTIN'), ['cost_price' => 100, 'dropship_fee' => 25, 'shipping_cost_estimate' => 40]);
        $this->offer($product, $this->supplier('SCUMP'), ['cost_price' => 130]);

        $chosen = $this->router()->route($product)->chosen();

        $this->assertSame('SCUMP', $chosen['supplier']->code);
        $this->assertSame(130.0, $chosen['landed']['unit_landed_cost']);
    }

    public function test_a_small_saving_does_not_beat_a_supplier_that_actually_has_the_part(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('BACKORDER'), ['cost_price' => 100, 'stock_status' => StockStatus::Backorder, 'stock_quantity' => 0]);
        $this->offer($product, $this->supplier('INSTOCK'), ['cost_price' => 105]);

        $result = $this->router()->route($product);

        // 100 × 1.09 (backorder 8% + two days at 0.5%) = 109, against 105 × 1.01 = 106.05.
        $this->assertSame('INSTOCK', $result->chosen()['supplier']->code);
        $this->assertContains('backorder +8%', $result->eligible->last()['adjustments']);
    }

    public function test_an_offer_that_cannot_be_costed_never_beats_one_that_can(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('NECOSTAT'), ['cost_price' => null]);
        $this->offer($product, $this->supplier('COSTAT'), ['cost_price' => 500]);

        $result = $this->router()->route($product);

        $this->assertSame('COSTAT', $result->chosen()['supplier']->code);
        $this->assertFalse($result->eligible->last()['landed']['complete']);
    }

    public function test_offers_that_cannot_fulfil_the_line_are_excluded_with_their_reason(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('EPUIZAT'), ['cost_price' => 90, 'stock_status' => StockStatus::OutOfStock]);
        $this->offer($product, $this->supplier('PUTIN'), ['cost_price' => 90, 'stock_quantity' => 1]);
        $this->offer($product, $this->supplier('BLOCAT'), ['cost_price' => 90, 'is_dropship_eligible' => false]);
        $this->offer($product, $this->supplier('DOARSK', ['allowed_countries' => ['SK']]), ['cost_price' => 90]);
        $this->offer($product, $this->supplier('BUN'), ['cost_price' => 120]);

        $result = $this->router()->route($product, quantity: 2, destinationCountry: 'RO');

        $this->assertSame(['BUN'], $result->eligible->map(fn (array $row): string => $row['supplier']->code)->all());
        $this->assertEqualsCanonicalizing([
            ['supplier' => 'EPUIZAT', 'reason' => 'stock_out_of_stock'],
            ['supplier' => 'PUTIN', 'reason' => 'insufficient_quantity'],
            ['supplier' => 'BLOCAT', 'reason' => 'article_not_dropshippable'],
            ['supplier' => 'DOARSK', 'reason' => 'destination_not_served'],
        ], $result->excluded);
    }

    public function test_a_paused_supplier_or_a_stale_offer_is_never_routed(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('OPRIT', ['is_active' => false]), ['cost_price' => 50]);
        $this->offer($product, $this->supplier('VECHI'), ['cost_price' => 50, 'stale_after' => now()->subHour()]);

        $result = $this->router()->route($product);

        $this->assertTrue($result->unavailable(), 'Sold through suppliers, yet no supplier can fulfil it.');
        $this->assertSame([], $result->excluded, 'They are not candidates at all, not merely excluded.');
    }

    public function test_a_product_with_no_supplier_is_own_stock_not_unavailable(): void
    {
        $result = $this->router()->route($this->product());

        $this->assertFalse($result->soldThroughSuppliers);
        $this->assertFalse($result->unavailable());
        $this->assertNull($result->chosen());
    }

    public function test_the_product_page_does_not_promise_stock_the_router_would_never_sell(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('OPRIT', ['is_active' => false]), ['cost_price' => 50]);
        $this->offer($product, $this->supplier('ACTIV'), ['cost_price' => 50, 'stock_status' => StockStatus::OutOfStock]);

        // The in-stock offer belongs to a paused supplier, so the honest answer is the
        // active supplier's: out of stock.
        $this->assertSame(StockStatus::OutOfStock, Availability::forProduct($product)->status);
    }

    private function router(): SupplierOfferRouter
    {
        return app(SupplierOfferRouter::class);
    }

    private function product(): Product
    {
        return Product::query()->create(['name' => 'Bară față', 'slug' => 'bara-'.Str::random(8)])->fresh();
    }

    /** @param array<string, mixed> $attributes */
    private function supplier(string $code, array $attributes = []): Supplier
    {
        return Supplier::query()->create([
            'name' => $code,
            'code' => $code,
            'protocol' => 'csv',
            'default_currency' => 'RON',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function offer(Product $product, Supplier $supplier, array $attributes): SupplierOffer
    {
        $supplierProduct = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'external_id' => $supplier->code.'-'.Str::random(6),
            'name' => $product->name,
        ]);

        // Defaults describe an offer that is sellable and fully costed, so each test only
        // states the one thing it is about.
        return SupplierOffer::query()->create([
            'supplier_product_id' => $supplierProduct->id,
            'currency' => 'RON',
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => 10,
            'shipping_cost_estimate' => 0,
            'dispatch_days_max' => 2,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
