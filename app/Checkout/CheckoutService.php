<?php

namespace App\Checkout;

use App\Commerce\RoutingResult;
use App\Commerce\SupplierOfferRouter;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
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
    public function __construct(
        private PaymentService $payments,
        private VehicleContext $vehicles,
        private SupplierOfferRouter $router,
    ) {}

    public function place(Cart $cart, array $customer, ShippingMethod $method, ?PaymentProvider $provider = null, array $paymentContext = []): Order
    {
        abort_unless($cart->status === 'active' && $cart->items()->exists(), 422, 'Coșul nu poate fi finalizat.');

        // Read before the transaction so the line stamp reflects what the customer was
        // shopping for, and so a session lookup never happens with a transaction open.
        $activeVehicleId = $this->vehicles->current()?->customerVehicleId;

        $cart->load('items.product', 'items.variant');
        $routes = $this->routeLines($cart, (string) ($customer['shipping']['country_code'] ?? 'RO'));

        $order = DB::transaction(function () use ($cart, $customer, $method, $activeVehicleId, $routes): Order {
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
                $route = $routes[$item->id];

                $order->items()->create([
                    'product_id' => $item->product_id, 'variant_id' => $item->variant_id,
                    'customer_vehicle_id' => $activeVehicleId,
                    'name' => $item->snapshot['name'] ?? $item->product->name,
                    'sku' => $item->snapshot['sku'] ?? $item->variant?->sku ?? $item->product->sku,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                    'line_total' => $lineTotals[$index]->toDecimal(),
                    'tax_rate' => $item->snapshot['tax_rate'] ?? 0,
                    ...$this->fulfilmentColumns($route, $currency),
                    'snapshot' => [...($item->snapshot ?? []), 'fulfilment' => $this->fulfilmentSnapshot($route)],
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
     * Chooses a supplier for every line before anything is written.
     *
     * An order used to be placed with no idea who would fulfil it: the supplier columns on
     * order_items existed and were never filled, so the shop took money for parts nobody had
     * been asked for, at a cost nobody had recorded. Routing here also refuses a line no
     * supplier can currently fulfil, while the cart is still intact and can be corrected.
     *
     * @return array<int, RoutingResult> keyed by cart item id
     */
    private function routeLines(Cart $cart, string $destinationCountry): array
    {
        $routes = [];

        foreach ($cart->items as $item) {
            $routes[$item->id] = $this->router->route($item->product, (int) $item->quantity, strtoupper($destinationCountry), $item->variant);
        }

        $unavailable = $cart->items
            ->filter(fn (CartItem $item): bool => $routes[$item->id]->unavailable())
            ->map(fn (CartItem $item): string => (string) ($item->snapshot['name'] ?? $item->product->name))
            ->values()
            ->all();

        if ($unavailable !== []) {
            throw CheckoutLineUnavailable::for($unavailable);
        }

        return $routes;
    }

    /**
     * The supplier and cost columns order_items has always had. unit_cost is written only
     * when the landed cost is complete and already in the order's currency: a partial or
     * converted-on-the-fly figure stored there would be read later as a real margin.
     *
     * @return array<string, mixed>
     */
    private function fulfilmentColumns(RoutingResult $route, string $currency): array
    {
        $chosen = $route->chosen();

        if ($chosen === null) {
            return [];
        }

        $landed = $chosen['landed'];

        return [
            'supplier_id' => $chosen['supplier']->id,
            'supplier_product_id' => $chosen['supplier_product']->id,
            'unit_cost' => $landed['complete'] && $landed['currency'] === $currency ? $landed['unit_landed_cost'] : null,
        ];
    }

    /**
     * Why this supplier, at what cost, and what else was available. Kept on the line so a
     * decision made at checkout can still be explained when the supplier's prices have long
     * since moved on.
     *
     * @return array<string, mixed>
     */
    private function fulfilmentSnapshot(RoutingResult $route): array
    {
        $chosen = $route->chosen();

        if ($chosen === null) {
            return ['mode' => 'own_stock', 'routed_at' => now()->toIso8601String()];
        }

        $offer = $chosen['offer'];
        $landed = $chosen['landed'];

        return [
            'mode' => 'supplier',
            'supplier_code' => $chosen['supplier']->code,
            'supplier_sku' => $chosen['supplier_product']->supplier_sku ?? $chosen['supplier_product']->external_id,
            'offer_id' => $offer->id,
            'supplier_cost' => $offer->cost_price === null ? null : (float) $offer->cost_price,
            'supplier_currency' => $offer->currency,
            'fx_rate' => $landed['fx_rate'],
            'fx_rate_date' => $landed['fx_rate_date'],
            'landed' => array_intersect_key($landed, array_flip([
                'currency', 'unit_cost', 'dropship_fee', 'handling_fee', 'freight', 'total', 'unit_landed_cost', 'complete', 'missing',
            ])),
            'effective_unit_cost' => $chosen['effective_unit_cost'],
            'adjustments' => $chosen['adjustments'],
            'alternatives' => $route->eligible->count() - 1,
            'excluded' => $route->excluded,
            'routed_at' => now()->toIso8601String(),
        ];
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
