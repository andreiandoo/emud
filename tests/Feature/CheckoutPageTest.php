<?php

namespace Tests\Feature;

use App\Livewire\Customer\Login;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Storefront\CartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The order route takes a {token}. Redirecting with any other parameter name made the URL
     * generator throw after the order had already been created and the cart converted, so the
     * customer saw an error for an order that had gone through.
     */
    public function test_placing_an_order_lands_on_its_confirmation_page(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_ok', 'status' => 'requires_action', 'client_secret' => 's'])]);
        $this->stripe();
        $method = $this->shippingMethod();
        $this->cartInSession();

        $component = Livewire::test(CheckoutPage::class)
            ->set('email', 'client@example.com')
            ->set('phone', '+40700000000')
            ->set('first_name', 'Andrei')
            ->set('last_name', 'Popescu')
            ->set('line_1', 'Str. Exemplu 1')
            ->set('city', 'Cluj-Napoca')
            ->set('shippingMethodId', $method->id)
            ->call('place')
            ->assertHasNoErrors()
            ->assertSet('failure', null);

        $order = Order::query()->latest('id')->sole();

        $component->assertRedirect(route('storefront.order', ['token' => $order->checkout_token]));
    }

    public function test_signing_in_keeps_the_basket_built_as_a_guest(): void
    {
        $user = User::factory()->create(['password' => 'parola-sigura-123']);
        $cart = $this->cartInSession();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'parola-sigura-123')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertSame($user->id, $cart->refresh()->user_id);
    }

    private function cartInSession(): Cart
    {
        $cart = app(CartManager::class)->current(create: true);
        $product = Product::create(['name' => 'Kit înălțare 50 mm', 'slug' => 'kit-'.Str::random(6)]);

        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 3559.00,
            'snapshot' => ['name' => $product->name, 'sku' => 'KIT-50', 'tax_rate' => 21],
        ]);

        return $cart;
    }

    private function shippingMethod(): ShippingMethod
    {
        return ShippingMethod::create([
            'code' => 'fan-standard',
            'name' => 'FAN Courier Standard',
            'base_price' => 25.00,
            'currency' => 'RON',
            'is_active' => true,
        ]);
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
}
