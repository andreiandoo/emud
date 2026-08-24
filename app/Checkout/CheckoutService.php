<?php

namespace App\Checkout;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(private PaymentService $payments) {}

    public function place(Cart $cart, array $customer, ShippingMethod $method, ?PaymentProvider $provider = null, array $paymentContext = []): Order
    {
        abort_unless($cart->status === 'active' && $cart->items()->exists(), 422, 'Coșul nu poate fi finalizat.');

        $order = DB::transaction(function () use ($cart, $customer, $method): Order {
            $cart->load('items.product', 'items.variant');
            $shipping = Address::create([...$customer['shipping'], 'user_id' => $cart->user_id, 'type' => 'shipping']);
            $billingData = $customer['billing'] ?? $customer['shipping'];
            $billing = Address::create([...$billingData, 'user_id' => $cart->user_id, 'type' => 'billing']);
            $subtotal = $cart->items->sum(fn ($item) => (float) $item->unit_price * $item->quantity);
            $shippingTotal = $method->priceFor($subtotal);

            $order = Order::create([
                'number' => 'EM-'.now()->format('ymd').'-'.strtoupper(Str::random(7)),
                'user_id' => $cart->user_id, 'billing_address_id' => $billing->id,
                'shipping_address_id' => $shipping->id, 'shipping_method_id' => $method->id,
                'checkout_token' => Str::uuid(), 'currency' => $cart->currency,
                'subtotal' => $subtotal, 'shipping_total' => $shippingTotal,
                'grand_total' => $subtotal + $shippingTotal,
                'customer_email' => $customer['email'], 'customer_phone' => $customer['phone'] ?? null,
                'customer_note' => $customer['note'] ?? null, 'placed_at' => now(),
            ]);
            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id, 'variant_id' => $item->variant_id,
                    'name' => $item->snapshot['name'] ?? $item->product->name,
                    'sku' => $item->snapshot['sku'] ?? $item->variant?->sku ?? $item->product->sku,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                    'line_total' => (float) $item->unit_price * $item->quantity,
                    'tax_rate' => $item->snapshot['tax_rate'] ?? 0, 'snapshot' => $item->snapshot,
                ]);
            }
            $cart->update(['status' => 'converted']);

            return $order;
        });

        $this->payments->start($order, $provider, $paymentContext);

        return $order->refresh();
    }
}
