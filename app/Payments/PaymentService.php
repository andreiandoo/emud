<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private PaymentGatewayRegistry $registry) {}

    public function start(Order $order, ?PaymentProvider $provider = null, array $context = []): PaymentTransaction
    {
        $provider ??= $this->registry->defaultProvider();
        abort_unless($provider->is_active, 422, 'Procesatorul de plăți este inactiv.');
        $idempotencyKey = $context['idempotency_key'] ?? "order-{$order->id}-payment";

        return DB::transaction(function () use ($order, $provider, $context, $idempotencyKey): PaymentTransaction {
            if ($existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }

            $result = $this->registry->gateway($provider)->start($provider, $order, [
                ...$context,
                'idempotency_key' => $idempotencyKey,
            ]);

            $transaction = PaymentTransaction::create([
                'uuid' => Str::uuid(),
                'order_id' => $order->id,
                'payment_provider_id' => $provider->id,
                'external_id' => $result->externalId,
                'idempotency_key' => $idempotencyKey,
                'status' => $result->status,
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'redirect_url' => $result->redirectUrl,
                'payload' => $result->payload,
                'processed_at' => $result->status === 'paid' ? now() : null,
            ]);

            $order->update(['payment_provider_id' => $provider->id]);

            return $transaction;
        });
    }
}
