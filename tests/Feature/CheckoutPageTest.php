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
use App\Storefront\AddressBook;
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

    public function test_an_order_can_be_invoiced_to_a_company(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_ok', 'status' => 'requires_action', 'client_secret' => 's'])]);
        $this->stripe();
        $method = $this->shippingMethod();
        $this->cartInSession();

        Livewire::test(CheckoutPage::class)
            ->set('email', 'client@example.com')
            ->set('phone', '+40700000000')
            ->set('first_name', 'Andrei')
            ->set('last_name', 'Popescu')
            ->set('line_1', 'Str. Exemplu 1')
            ->set('city', 'Cluj-Napoca')
            ->set('billingType', 'company')
            ->set('billing.company', 'Off Road Garage SRL')
            ->set('billing.vat_number', 'RO12345678')
            ->set('billing.line_1', 'Str. Depozitului 4')
            ->set('billing.city', 'Brașov')
            ->set('shippingMethodId', $method->id)
            ->call('place')
            ->assertHasNoErrors()
            ->assertSet('failure', null);

        $order = Order::query()->with(['billingAddress', 'shippingAddress'])->latest('id')->sole();

        $this->assertSame('Off Road Garage SRL', $order->billingAddress->company);
        $this->assertSame('RO12345678', $order->billingAddress->vat_number);
        $this->assertSame('Brașov', $order->billingAddress->city);
        // The person ordering is the contact on the firm's invoice.
        $this->assertSame('Andrei', $order->billingAddress->first_name);
        $this->assertSame('Cluj-Napoca', $order->shippingAddress->city);
    }

    public function test_a_returning_customer_finds_their_address_filled_in(): void
    {
        $user = User::factory()->create();
        app(AddressBook::class)->saveShipping($user, [
            'first_name' => 'Andrei',
            'last_name' => 'Popescu',
            'line_1' => 'Str. Exemplu 1',
            'city' => 'Cluj-Napoca',
        ]);
        $this->actingAs($user);
        $this->shippingMethod();
        $this->cartInSession();

        Livewire::test(CheckoutPage::class)
            ->assertSet('first_name', 'Andrei')
            ->assertSet('city', 'Cluj-Napoca');
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
