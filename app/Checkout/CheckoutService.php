<?php

namespace App\Checkout;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\ShippingMethod;
use App\Notifications\OrderPlaced;
use App\Payments\PaymentService;
use App\Storefront\VehicleContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class CheckoutService
{
    public function __construct(private PaymentService $payments, private VehicleContext $vehicles) {}

    public function place(Cart $cart, array $customer, ShippingMethod $method, ?PaymentProvider $provider = null, array $paymentContext = []): Order
    {
        abort_unless($cart->status === 'active' && $cart->items()->exists(), 422, 'Coșul nu poate fi finalizat.');

        // Read before the transaction so the line stamp reflects what the customer was
        // shopping for, and so a session lookup never happens with a transaction open.
        $activeVehicleId = $this->vehicles->current()?->customerVehicleId;

        $order = DB::transaction(function () use ($cart, $customer, $method, $activeVehicleId): Order {
            $cart->load('items.product', 'items.variant');
            $shipping = Address::create([...$customer['shipping'], 'user_id' => $cart->user_id, 'type' => 'shipping']);
            $billingData = $customer['billing'] ?? $customer['shipping'];
            $billing = Address::create([...$billingData, 'user_id' => $cart->user_id, 'type' => 'billing']);
            $currency = (string) $cart->currency;

            // Every total is built from integer minor units. Summed as floats, a long order
            // drifted by a ban or two and the amount charged could differ from the amount stored.
            $lineTotals = $cart->items->map(
                fn ($item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity)
            );

            $subtotal = Money::sum($lineTotals, $currency);
            $shippingTotal = $method->priceFor($subtotal);
            $grandTotal = $subtotal->plus($shippingTotal);

            $order = Order::create([
                'number' => 'EM-'.now()->format('ymd').'-'.strtoupper(Str::random(7)),
                'user_id' => $cart->user_id, 'billing_address_id' => $billing->id,
                'shipping_address_id' => $shipping->id, 'shipping_method_id' => $method->id,
                'checkout_token' => Str::uuid(), 'currency' => $currency,
                'subtotal' => $subtotal->toDecimal(), 'shipping_total' => $shippingTotal->toDecimal(),
                'grand_total' => $grandTotal->toDecimal(),
                'customer_email' => $customer['email'], 'customer_phone' => $customer['phone'] ?? null,
                'customer_note' => $customer['note'] ?? null, 'placed_at' => now(),
            ]);

            foreach ($cart->items as $index => $item) {
                $order->items()->create([
                    'product_id' => $item->product_id, 'variant_id' => $item->variant_id,
                    'customer_vehicle_id' => $activeVehicleId,
                    'name' => $item->snapshot['name'] ?? $item->product->name,
                    'sku' => $item->snapshot['sku'] ?? $item->variant?->sku ?? $item->product->sku,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                    'line_total' => $lineTotals[$index]->toDecimal(),
                    'tax_rate' => $item->snapshot['tax_rate'] ?? 0, 'snapshot' => $item->snapshot,
                ]);
            }

            return $order;
        });

        // The cart stays active until the payment has actually been initiated. Converting it
        // inside the order transaction left a customer whose gateway call then failed with an
        // emptied cart, an order nobody was paying for, and no way to retry: place() requires
        // an active cart.
        try {
            $this->payments->start($order, $provider, $paymentContext);
        } catch (Throwable $exception) {
            $order->update(['status' => 'failed', 'payment_status' => 'failed']);

            throw $exception;
        }

        $cart->update(['status' => 'converted']);

        $this->confirmByEmail($order);

        return $order->refresh();
    }

    /**
     * Addressed to the email captured on the order rather than to an account, because a guest
     * checkout has no account to notify. Failures are swallowed: the order is already placed and
     * paid for, and refusing to return it because a mail server was unreachable would leave the
     * customer thinking nothing happened.
     */
    private function confirmByEmail(Order $order): void
    {
        try {
            Notification::route('mail', $order->customer_email)
                ->notify(new OrderPlaced($order->load('items')));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
