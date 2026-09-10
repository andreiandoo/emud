<?php

namespace App\Livewire\Storefront;

use App\Checkout\CheckoutService;
use App\Models\CartItem;
use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Storefront\AddressBook;
use App\Storefront\CartManager;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts::storefront', ['fullWidth' => true])]
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

    /** Who the invoice is made out to: 'person' or 'company'. */
    public string $billingType = 'person';

    /** A person invoiced at the delivery address needs no second address. */
    public bool $billingSame = true;

    /** @var array<string, string> */
    public array $billing = [];

    /** A signed-in customer keeps what they typed here for next time, unless they untick it. */
    public bool $saveDetails = true;

    public ?int $shippingMethodId = null;

    public ?string $failure = null;

    public function mount(CartManager $carts, AddressBook $book): void
    {
        $cart = $carts->current();

        if ($cart === null || ! $cart->items()->exists()) {
            $this->redirectRoute('storefront.cart');

            return;
        }

        $this->billing = AddressBook::form(null);

        if ($user = auth()->user()) {
            $this->email = (string) $user->email;
            $this->phone = (string) $user->phone;
            $this->prefill($book, $user);
        }

        $this->shippingMethodId ??= ShippingMethod::query()->where('is_active', true)->orderBy('position')->value('id');
    }

    public function place(CartManager $carts, CheckoutService $checkout, AddressBook $book)
    {
        $invoice = $this->invoiceKind();

        $data = $this->validate([
            'email' => ['required', 'email'],
            'phone' => ['required', 'string', 'max:32'],
            ...AddressBook::rules(''),
            'note' => ['nullable', 'string', 'max:500'],
            'shippingMethodId' => ['required', 'integer', 'exists:shipping_methods,id'],
            ...($invoice === null ? [] : AddressBook::rules('billing', $invoice)),
        ], [...AddressBook::messages(''), ...AddressBook::messages('billing')]);

        $cart = $carts->current();

        if ($cart === null || ! $cart->items()->exists()) {
            return $this->redirectRoute('storefront.cart');
        }

        $method = ShippingMethod::query()->where('is_active', true)->findOrFail($data['shippingMethodId']);

        $shipping = [...AddressBook::shippingDetails($data), 'phone' => $data['phone']];

        // A firm's invoice names the person ordering as its contact, so the delivery name
        // travels with it.
        $billing = $invoice === null ? null : AddressBook::billingDetails($invoice, $this->billing, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ]);

        $customer = [
            'email' => $data['email'],
            'phone' => $data['phone'],
            'note' => $data['note'] ?: null,
            'shipping' => $shipping,
        ];

        // Left out rather than copied when it is the delivery address: CheckoutService bills
        // the delivery address when no billing one is given.
        if ($billing !== null) {
            $customer['billing'] = [...$billing, 'phone' => $data['phone']];
        }

        try {
            $order = $checkout->place($cart, $customer, $method);
        } catch (Throwable $exception) {
            // The cart is deliberately left untouched when placing fails, so the customer can
            // retry rather than losing what they assembled. CheckoutService guarantees that.
            $this->failure = 'Comanda nu a putut fi finalizată: '.$exception->getMessage();

            return null;
        }

        $this->remember($book, $shipping, $billing);

        return $this->redirectRoute('storefront.order', ['token' => $order->checkout_token]);
    }

    public function render(CartManager $carts)
    {
        $cart = $carts->current();
        $items = $cart?->items()->with('product')->get() ?? collect();
        $currency = (string) ($cart?->currency ?? config('emud.catalog.default_currency', 'RON'));
        $subtotal = Money::sum(
            $items->map(fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity)),
            $currency,
        );
        $method = $this->shippingMethodId === null ? null : ShippingMethod::find($this->shippingMethodId);

        return view('livewire.storefront.checkout-page', [
            'items' => $items,
            'subtotal' => $subtotal,
            'lineTotal' => fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity),
            // Recomputed server-side on every render; the browser never supplies a total.
            'shippingTotal' => $method?->priceFor($subtotal) ?? Money::zero($currency),
            'methods' => ShippingMethod::query()->where('is_active', true)->orderBy('position')->get(),
            'providers' => PaymentProvider::query()->where('is_active', true)->orderBy('position')->get(),
            'currency' => $currency,
        ]);
    }

    /** What the customer saved on their account, so a returning customer types nothing twice. */
    private function prefill(AddressBook $book, User $user): void
    {
        if ($saved = $book->shipping($user)) {
            $this->first_name = (string) $saved->first_name;
            $this->last_name = (string) $saved->last_name;
            $this->line_1 = (string) $saved->line_1;
            $this->city = (string) $saved->city;
            $this->county = (string) $saved->county;
            $this->postal_code = (string) $saved->postal_code;
        }

        if ($billing = $book->billing($user)) {
            $this->billing = AddressBook::form($billing);
            $this->billingType = filled($billing->company) ? 'company' : 'person';
            $this->billingSame = false;
        }
    }

    /** Null when the invoice goes to the person at the delivery address. */
    private function invoiceKind(): ?string
    {
        if ($this->billingType === 'company') {
            return 'company';
        }

        return $this->billingSame ? null : 'person';
    }

    /**
     * Saved after the order is placed, never before: a customer whose order failed has not
     * finished telling us anything. A failure here is reported and swallowed — the order is
     * already placed, and the address book is only a convenience.
     *
     * @param  array<string, mixed>  $shipping
     * @param  array<string, mixed>|null  $billing
     */
    private function remember(AddressBook $book, array $shipping, ?array $billing): void
    {
        $user = auth()->user();

        if ($user === null || ! $this->saveDetails) {
            return;
        }

        rescue(function () use ($book, $user, $shipping, $billing): void {
            $book->saveShipping($user, $shipping);
            $book->saveBilling($user, $billing);
        });
    }
}
