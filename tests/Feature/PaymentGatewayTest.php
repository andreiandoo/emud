<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_payment_intent_is_created_idempotently(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_test_123', 'status' => 'requires_action', 'client_secret' => 'secret'], 200)]);
        $provider = PaymentProvider::create([
            'code' => 'stripe', 'name' => 'Stripe', 'driver' => 'stripe', 'is_active' => true,
            'is_default' => true, 'credentials' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test'],
        ]);
        $order = Order::create(['number' => 'EM-TEST', 'customer_email' => 'client@example.com', 'grand_total' => 123.45, 'currency' => 'RON']);

        $first = app(PaymentService::class)->start($order, $provider);
        $second = app(PaymentService::class)->start($order, $provider);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('pi_test_123', $first->external_id);
        Http::assertSentCount(1);
    }
}
