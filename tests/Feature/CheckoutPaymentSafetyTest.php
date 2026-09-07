<?php

namespace Tests\Feature;

use App\Checkout\CheckoutService;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class CheckoutPaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Holding a transaction open across the provider call pins a database connection for the
     * provider's full latency, and any later failure would roll the local row back while the
     * payment stayed live at the provider.
     */
    public function test_the_provider_is_not_called_inside_a_database_transaction(): void
    {
        $levels = [];

        Http::fake(function (Request $request) use (&$levels) {
            $levels[] = DB::transactionLevel();

            return Http::response(['id' => 'pi_1', 'status' => 'requires_action', 'client_secret' => 's']);
        });

        app(PaymentService::class)->start($this->order(), $this->stripe());

        $this->assertSame([0], $levels, 'The gateway call must run outside any open transaction.');
    }

    public function test_a_failed_provider_call_still_leaves_a_local_record(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => 'boom'], 500)]);
        $order = $this->order();

        try {
            app(PaymentService::class)->start($order, $this->stripe());
            $this->fail('The gateway failure should have propagated.');
        } catch (Throwable) {
            // expected
        }

        $transaction = PaymentTransaction::query()->where('order_id', $order->id)->sole();

        $this->assertSame('failed', $transaction->status);
        $this->assertNotNull($transaction->error_message);
        $this->assertSame("order-{$order->id}-payment", $transaction->idempotency_key);
    }

    public function test_a_failed_payment_can_be_retried_under_the_same_idempotency_key(): void
    {
        Http::fake(['api.stripe.com/*' => Http::sequence()
            ->push(['error' => 'boom'], 500)
            ->push(['id' => 'pi_2', 'status' => 'requires_action', 'client_secret' => 's']),
        ]);
        $order = $this->order();
        $provider = $this->stripe();

        try {
            app(PaymentService::class)->start($order, $provider);
        } catch (Throwable) {
            // expected
        }

        $retried = app(PaymentService::class)->start($order, $provider);

        $this->assertSame('pi_2', $retried->external_id);
        $this->assertNull($retried->error_message);
        $this->assertSame(1, PaymentTransaction::query()->where('order_id', $order->id)->count());

        // The same key reaches the provider on the retry, so a provider honouring it cannot
        // create a second payment for this order.
        Http::assertSent(fn (Request $request): bool => $request->header('Idempotency-Key')[0] === "order-{$order->id}-payment");
    }

    public function test_an_already_started_payment_is_never_sent_to_the_provider_again(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_3', 'status' => 'requires_action', 'client_secret' => 's'])]);
        $order = $this->order();
        $provider = $this->stripe();

        $first = app(PaymentService::class)->start($order, $provider);
        $second = app(PaymentService::class)->start($order, $provider);

        $this->assertSame($first->id, $second->id);
        Http::assertSentCount(1);
    }

    /**
     * place() refuses a cart that is not active, so converting it before the payment was
     * actually initiated left the customer unable to retry their own order.
     */
    public function test_a_failed_payment_leaves_the_cart_active_so_checkout_can_be_retried(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => 'boom'], 500)]);
        [$cart, $method] = $this->cart();
        $this->stripe();

        try {
            app(CheckoutService::class)->place($cart, $this->customer(), $method);
            $this->fail('The gateway failure should have propagated.');
        } catch (Throwable) {
            // expected
        }

        $this->assertSame('active', $cart->refresh()->status);

        $order = Order::query()->latest('id')->sole();
        $this->assertSame('failed', $order->status);
        $this->assertSame('failed', $order->payment_status);
    }

    public function test_a_started_payment_converts_the_cart(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_4', 'status' => 'requires_action', 'client_secret' => 's'])]);
        [$cart, $method] = $this->cart();
        $this->stripe();

        $order = app(CheckoutService::class)->place($cart, $this->customer(), $method);

        $this->assertSame('converted', $cart->refresh()->status);
        $this->assertSame('pi_4', PaymentTransaction::query()->where('order_id', $order->id)->sole()->external_id);
    }

    private function stripe(): PaymentProvider
    {
        return PaymentProvider::create([
            'code' => 'stripe',
            'name' => 'Stripe',
            'driver' => 'stripe',
            'is_active' => true,
            'is_default' => true,
            'credentials' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test'],
        ]);
    }

    private function order(): Order
    {
        return Order::create([
            'number' => 'EM-'.Str::upper(Str::random(6)),
            'customer_email' => 'client@example.com',
            'grand_total' => 123.45,
            'currency' => 'RON',
        ]);
    }

    /** @return array{0: Cart, 1: ShippingMethod} */
    private function cart(): array
    {
        $product = Product::create(['name' => 'Bară de protecție', 'slug' => 'bara-'.Str::random(6)]);
        $cart = Cart::create(['token' => Str::uuid(), 'status' => 'active', 'currency' => 'RON']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 250.00,
            'snapshot' => ['name' => $product->name, 'sku' => 'SKU-1', 'tax_rate' => 19],
        ]);

        $method = ShippingMethod::create([
            'code' => 'fan-standard',
            'name' => 'FAN Courier Standard',
            'base_price' => 25.00,
            'currency' => 'RON',
            'is_active' => true,
        ]);

        return [$cart, $method];
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
