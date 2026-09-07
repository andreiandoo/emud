<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    /**
     * The only state a payment may be started again from. Anything else means the provider has
     * already been asked to create this payment, so re-sending it risks a second charge.
     */
    private const RETRYABLE_STATUS = 'failed';

    public function __construct(private PaymentGatewayRegistry $registry) {}

    public function start(Order $order, ?PaymentProvider $provider = null, array $context = []): PaymentTransaction
    {
        $provider ??= $this->registry->defaultProvider();
        abort_unless($provider->is_active, 422, 'Procesatorul de plăți este inactiv.');
        $idempotencyKey = $context['idempotency_key'] ?? "order-{$order->id}-payment";

        // The local record is written before the provider is contacted, so a payment can never
        // exist at the provider without a row here. Reserving through the unique index also
        // makes it the concurrency guard: two simultaneous requests cannot both reach the
        // gateway and create two payments for one order.
        [$transaction, $mayStart] = $this->reserve($order, $provider, $idempotencyKey);

        if (! $mayStart) {
            return $transaction;
        }

        try {
            // Deliberately outside any transaction. Holding one open across an external call
            // pins a connection for the provider's full latency, and a failure after the call
            // succeeded would roll back the local row while the payment stayed live.
            $result = $this->registry->gateway($provider)->start($provider, $order, [
                ...$context,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (Throwable $exception) {
            $transaction->update([
                'status' => self::RETRYABLE_STATUS,
                'error_message' => Str::limit($exception->getMessage(), 1000),
            ]);

            throw $exception;
        }

        $transaction->update([
            'external_id' => $result->externalId,
            'status' => $result->status,
            'redirect_url' => $result->redirectUrl,
            'payload' => $result->payload,
            'error_message' => null,
            'processed_at' => $result->status === 'paid' ? now() : null,
        ]);

        return $transaction->refresh();
    }

    /**
     * @return array{0: PaymentTransaction, 1: bool} the transaction, and whether this caller is
     *                                               the one allowed to contact the provider
     */
    private function reserve(Order $order, PaymentProvider $provider, string $idempotencyKey): array
    {
        try {
            return DB::transaction(function () use ($order, $provider, $idempotencyKey): array {
                $existing = PaymentTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->status !== self::RETRYABLE_STATUS) {
                        return [$existing, false];
                    }

                    // Retrying reuses the same idempotency key, which is also handed to the
                    // gateway, so a provider that honours it will not create a second payment.
                    $existing->update(['status' => 'pending', 'error_message' => null]);

                    return [$existing, true];
                }

                $transaction = PaymentTransaction::create([
                    'uuid' => Str::uuid(),
                    'order_id' => $order->id,
                    'payment_provider_id' => $provider->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'pending',
                    'amount' => $order->grand_total,
                    'currency' => $order->currency,
                ]);

                $order->update(['payment_provider_id' => $provider->id]);

                return [$transaction, true];
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request reserved the key between the lookup and the insert. It owns
            // the provider call; this one reports whatever that request recorded.
            return [PaymentTransaction::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(), false];
        }
    }
}
