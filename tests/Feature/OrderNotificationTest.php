<?php

namespace Tests\Feature;

use App\Checkout\CheckoutService;
use App\Models\Cart;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Notifications\OrderPlaced;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_placed_order_is_confirmed_by_email(): void
    {
        Notification::fake();
        $this->stripe();

        $order = app(CheckoutService::class)->place($this->cart(), $this->customer(), $this->method());

        Notification::assertSentOnDemand(
            OrderPlaced::class,
            fn (OrderPlaced $notification, array $channels, object $notifiable): bool => $notification->order->is($order)
                && $notifiable->routes['mail'] === 'client@example.com'
        );
    }

    /**
     * A guest checkout has no account, so the confirmation is addressed to the email captured
     * on the order itself.
     */
    public function test_a_guest_order_is_still_confirmed(): void
    {
        Notification::fake();
        $this->stripe();

        app(CheckoutService::class)->place($this->cart(), $this->customer(), $this->method());

        Notification::assertSentOnDemand(OrderPlaced::class);
        Notification::assertCount(1);
    }

    /**
     * The order is already placed by then. Refusing to return it because a mail server was
     * unreachable would leave the customer believing nothing happened.
     */
    public function test_a_failing_mailer_does_not_lose_the_order(): void
    {
        $this->stripe();
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('mail down'));

        try {
            $order = app(CheckoutService::class)->place($this->cart(), $this->customer(), $this->method());
        } catch (Throwable $exception) {
            $this->fail('A mail failure must not propagate: '.$exception->getMessage());
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_no_confirmation_is_sent_when_the_payment_cannot_start(): void
    {
        Notification::fake();
        Http::fake(['api.stripe.com/*' => Http::response(['error' => 'down'], 500)]);
        $this->stripe(fake: false);

        try {
            app(CheckoutService::class)->place($this->cart(), $this->customer(), $this->method());
        } catch (Throwable) {
            // expected
        }

        Notification::assertNothingSent();
    }


    /**
     * The totals a customer is charged must match the totals stored, to the ban. Computed as
     * floats these drifted apart on orders with many lines.
     */
    public function test_order_totals_are_exact(): void
    {
        Notification::fake();
        $this->stripe();

        $cart = Cart::create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);

        for ($i = 0; $i < 7; $i++) {
            $product = Product::create([
                'name' => 'Piesa '.$i,
                'slug' => 'piesa-'.$i.'-'.Str::random(5),
                'status' => 'active',
                'published_at' => now(),
            ]);
            ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(6), 'retail_price' => '19.99', 'is_active' => true]);
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 3, 'unit_price' => '19.99']);
        }

        $method = ShippingMethod::create([
            'code' => 'exact-'.Str::random(4),
            'name' => 'Curier',
            'base_price' => '24.99',
            'currency' => 'RON',
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->place($cart, $this->customer(), $method);

        // 7 lines x 3 x 19.99 = 419.79, plus 24.99 shipping.
        $this->assertSame('419.79', Money::of($order->subtotal, 'RON')->toDecimal());
        $this->assertSame('24.99', Money::of($order->shipping_total, 'RON')->toDecimal());
        $this->assertSame('444.78', Money::of($order->grand_total, 'RON')->toDecimal());
    }

    public function test_free_shipping_applies_exactly_at_the_threshold(): void
    {
        Notification::fake();
        $this->stripe();

        $product = Product::create(['name' => 'Prag', 'slug' => 'prag-'.Str::random(5), 'status' => 'active', 'published_at' => now()]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(6), 'retail_price' => '300.00', 'is_active' => true]);

        $cart = Cart::create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '300.00']);

        $method = ShippingMethod::create([
            'code' => 'prag-'.Str::random(4),
            'name' => 'Curier',
            'base_price' => '25.00',
            'free_over' => '300.00',
            'currency' => 'RON',
            'is_active' => true,
        ]);

        $order = app(CheckoutService::class)->place($cart, $this->customer(), $method);

        $this->assertSame('0.00', Money::of($order->shipping_total, 'RON')->toDecimal());
    }

    private function stripe(bool $fake = true): PaymentProvider
    {
        if ($fake) {
            Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_1', 'status' => 'requires_action', 'client_secret' => 's'])]);
        }

        return PaymentProvider::create([
            'code' => 'stripe',
            'name' => 'Stripe',
            'driver' => 'stripe',
            'is_active' => true,
            'is_default' => true,
            'credentials' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec'],
        ]);
    }

    private function cart(): Cart
    {
        $product = Product::create([
            'name' => 'Bară de protecție',
            'slug' => 'bara-'.Str::random(6),
            'status' => 'active',
            'published_at' => now(),
        ]);

        ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(6), 'retail_price' => 250, 'is_active' => true]);

        $cart = Cart::create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 250,
            'snapshot' => ['name' => $product->name, 'sku' => 'SKU-1'],
        ]);

        return $cart;
    }

    private function method(): ShippingMethod
    {
        return ShippingMethod::create([
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
