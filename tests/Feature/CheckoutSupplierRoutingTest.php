<?php

namespace Tests\Feature;

use App\Checkout\CheckoutLineUnavailable;
use App\Checkout\CheckoutService;
use App\Enums\StockStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutSupplierRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_1', 'status' => 'requires_action', 'client_secret' => 's'])]);

        PaymentProvider::query()->create([
            'code' => 'stripe',
            'name' => 'Stripe',
            'driver' => 'stripe',
            'is_active' => true,
            'is_default' => true,
            'credentials' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec'],
        ]);
    }

    public function test_every_line_records_the_supplier_it_was_routed_to_and_what_it_cost(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('IEFTIN'), ['cost_price' => 100, 'dropship_fee' => 25, 'shipping_cost_estimate' => 40]);
        $scump = $this->offer($product, $this->supplier('SCUMP'), ['cost_price' => 130]);

        $item = $this->place($product)->items()->sole();

        $this->assertSame($scump->supplierProduct->supplier_id, $item->supplier_id);
        $this->assertSame($scump->supplier_product_id, $item->supplier_product_id);
        $this->assertEqualsWithDelta(130.0, (float) $item->unit_cost, 0.0001);

        $fulfilment = $item->snapshot['fulfilment'];
        $this->assertSame('supplier', $fulfilment['mode']);
        $this->assertSame('SCUMP', $fulfilment['supplier_code']);
        $this->assertSame(1, $fulfilment['alternatives']);
        $this->assertTrue($fulfilment['landed']['complete']);
        $this->assertSame('SKU-1', $item->snapshot['sku'], 'The cart snapshot is kept, not replaced.');
    }

    public function test_a_line_no_supplier_can_fulfil_is_refused_before_anything_is_written(): void
    {
        $product = $this->product('Troliu 12V');
        $this->offer($product, $this->supplier('EPUIZAT'), ['cost_price' => 900, 'stock_status' => StockStatus::OutOfStock]);
        $cart = $this->cart($product);

        try {
            app(CheckoutService::class)->place($cart, $this->customer(), $this->method());
            $this->fail('An unfulfillable line must stop the order.');
        } catch (CheckoutLineUnavailable $exception) {
            $this->assertStringContainsString('Troliu 12V', $exception->getMessage());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Address::query()->count());
        $this->assertSame('active', $cart->fresh()->status, 'The customer must be able to fix the basket and retry.');
        Http::assertNothingSent();
    }

    public function test_an_own_stock_product_still_checks_out_without_a_supplier(): void
    {
        $item = $this->place($this->product())->items()->sole();

        $this->assertNull($item->supplier_id);
        $this->assertNull($item->unit_cost);
        $this->assertSame('own_stock', $item->snapshot['fulfilment']['mode']);
    }

    public function test_a_supplier_that_does_not_serve_the_destination_is_passed_over(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('DOARSK', ['allowed_countries' => ['SK']]), ['cost_price' => 50]);
        $this->offer($product, $this->supplier('RO'), ['cost_price' => 80]);

        $fulfilment = $this->place($product)->items()->sole()->snapshot['fulfilment'];

        $this->assertSame('RO', $fulfilment['supplier_code']);
        $this->assertContains(['supplier' => 'DOARSK', 'reason' => 'destination_not_served'], $fulfilment['excluded']);
    }

    public function test_a_cost_that_cannot_be_completed_is_not_stored_as_the_line_cost(): void
    {
        $product = $this->product();
        $this->offer($product, $this->supplier('FARACOST'), ['cost_price' => null]);

        $item = $this->place($product)->items()->sole();

        $this->assertNotNull($item->supplier_id, 'The supplier can still fulfil it.');
        $this->assertNull($item->unit_cost, 'A partial figure stored as unit_cost would later read as a real margin.');
        $this->assertFalse($item->snapshot['fulfilment']['landed']['complete']);
        $this->assertContains('cost_price', $item->snapshot['fulfilment']['landed']['missing']);
    }

    private function place(Product $product): Order
    {
        return app(CheckoutService::class)->place($this->cart($product), $this->customer(), $this->method());
    }

    private function product(string $name = 'Bară de protecție'): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
            'published_at' => now(),
        ]);

        ProductVariant::query()->create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(6), 'retail_price' => 250, 'is_active' => true]);

        return $product;
    }

    private function cart(Product $product): Cart
    {
        $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 250,
            'snapshot' => ['name' => $product->name, 'sku' => 'SKU-1'],
        ]);

        return $cart;
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

        return SupplierOffer::query()->create([
            'supplier_product_id' => $supplierProduct->id,
            'currency' => 'RON',
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => 10,
            'shipping_cost_estimate' => 0,
            'dispatch_days_max' => 2,
            'is_active' => true,
            ...$attributes,
        ])->load('supplierProduct');
    }

    private function method(): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'code' => 'fan-'.Str::random(4),
            'name' => 'FAN Courier',
            'base_price' => 25,
            'currency' => 'RON',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function customer(): array
    {
        return [
            'email' => 'client@example.com',
            'phone' => '+40700000000',
            'shipping' => [
                'first_name' => 'Andrei',
                'last_name' => 'Popescu',
                'line_1' => 'Str. Exemplu 1',
                'city' => 'Cluj-Napoca',
                'country_code' => 'RO',
            ],
        ];
    }
}
