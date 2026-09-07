<?php

namespace App\Livewire\Storefront;

use App\Checkout\CheckoutService;
use App\Models\CartItem;
use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Storefront\CartManager;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts::storefront')]
class CheckoutPage extends Component
{
    public string $email = '';

    public string $phone = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $line_1 = '';

    public string $city = '';

    public string $county = '';

    public string $postal_code = '';

    public string $note = '';

    public ?int $shippingMethodId = null;

    public ?string $failure = null;

    public function mount(CartManager $carts): void
    {
        $cart = $carts->current();

        if ($cart === null || ! $cart->items()->exists()) {
            $this->redirectRoute('storefront.cart');

            return;
        }

        if ($user = auth()->user()) {
            $this->email = (string) $user->email;
            $this->phone = (string) $user->phone;
        }

        $this->shippingMethodId ??= ShippingMethod::query()->where('is_active', true)->orderBy('position')->value('id');
    }

    public function place(CartManager $carts, CheckoutService $checkout)
    {
        $data = $this->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'line_1' => ['required', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:80'],
            'county' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:500'],
            'shippingMethodId' => ['required', 'integer', 'exists:shipping_methods,id'],
        ]);

        $cart = $carts->current();

        if ($cart === null || ! $cart->items()->exists()) {
            return $this->redirectRoute('storefront.cart');
        }

        $method = ShippingMethod::query()->where('is_active', true)->findOrFail($data['shippingMethodId']);

        try {
            $order = $checkout->place($cart, [
                'email' => $data['email'],
                'phone' => $data['phone'],
                'note' => $data['note'] ?: null,
                'shipping' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'line_1' => $data['line_1'],
                    'city' => $data['city'],
                    'county' => $data['county'] ?: null,
                    'postal_code' => $data['postal_code'] ?: null,
                    'country_code' => 'RO',
                    'phone' => $data['phone'],
                ],
            ], $method);
        } catch (Throwable $exception) {
            // The cart is deliberately left untouched when placing fails, so the customer can
            // retry rather than losing what they assembled. CheckoutService guarantees that.
            $this->failure = 'Comanda nu a putut fi finalizată: '.$exception->getMessage();

            return null;
        }

        return $this->redirectRoute('storefront.order', ['order' => $order->checkout_token]);
    }

    public function render(CartManager $carts)
    {
        $cart = $carts->current();
        $items = $cart?->items()->with('product')->get() ?? collect();
        $subtotal = $items->sum(fn (CartItem $item) => (float) $item->unit_price * $item->quantity);
        $method = $this->shippingMethodId === null ? null : ShippingMethod::find($this->shippingMethodId);

        return view('livewire.storefront.checkout-page', [
            'items' => $items,
            'subtotal' => $subtotal,
            // Recomputed server-side on every render; the browser never supplies a total.
            'shippingTotal' => $method?->priceFor($subtotal) ?? 0.0,
            'methods' => ShippingMethod::query()->where('is_active', true)->orderBy('position')->get(),
            'providers' => PaymentProvider::query()->where('is_active', true)->orderBy('position')->get(),
            'currency' => $cart?->currency ?? config('emud.catalog.default_currency', 'RON'),
        ]);
    }
}
