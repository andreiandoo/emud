<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\PaymentResult;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeGateway implements PaymentGateway
{
    public function start(PaymentProvider $provider, Order $order, array $context = []): PaymentResult
    {
        $credentials = $provider->credentials ?? [];
        $secret = $credentials['secret_key'] ?? null;

        if (! $secret) {
            throw new RuntimeException('Cheia secretă Stripe nu este configurată.');
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->withHeaders(['Idempotency-Key' => $context['idempotency_key'] ?? "order-{$order->id}"])
            ->post('https://api.stripe.com/v1/payment_intents', [
                // Exact minor units. Multiplying a float by 100 and rounding could charge a
                // ban more or less than the order records.
                'amount' => Money::of($order->grand_total, (string) $order->currency)->toMinor(),
                'currency' => strtolower($order->currency),
                'receipt_email' => $order->customer_email,
                'description' => "Comanda {$order->number}",
                'metadata[order_id]' => (string) $order->id,
                'metadata[order_number]' => $order->number,
                'automatic_payment_methods[enabled]' => 'true',
            ])->throw()->json();

        return new PaymentResult(
            externalId: $response['id'],
            status: $response['status'] ?? 'requires_payment_method',
            payload: ['client_secret' => $response['client_secret'] ?? null],
        );
    }

    public function verifyWebhook(PaymentProvider $provider, string $payload, array $headers): bool
    {
        $signature = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;

        if ($this->webhookSecrets($provider) === [] || ! is_string($signature) || $signature === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        // Stripe sends every valid v1 signature in one header, which is how a rotated secret
        // stays verifiable during the overlap. Collapsing the header into a map kept only the
        // last one, so a rotation whose valid signature was not last was rejected as forged.
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && $value !== null) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        foreach ($this->webhookSecrets($provider) as $candidate) {
            $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $candidate);

            foreach ($signatures as $received) {
                if (hash_equals($expected, $received)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Rotating an endpoint secret means both the old and the new one are live for a window, so
     * verification has to accept either. `webhook_secrets` holds the rotation set;
     * `webhook_secret` remains supported as the single-secret case.
     *
     * @return list<string>
     */
    private function webhookSecrets(PaymentProvider $provider): array
    {
        $credentials = $provider->credentials ?? [];
        $secrets = array_merge(
            (array) ($credentials['webhook_secrets'] ?? []),
            [$credentials['webhook_secret'] ?? null],
        );

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $secret): string => is_string($secret) ? trim($secret) : '', $secrets),
            static fn (string $secret): bool => $secret !== '',
        )));
    }

    public function webhookReference(array $payload): ?string
    {
        return data_get($payload, 'data.object.id');
    }

    public function webhookStatus(array $payload): string
    {
        return match ($payload['type'] ?? null) {
            'payment_intent.succeeded' => 'paid',
            'payment_intent.payment_failed', 'payment_intent.canceled' => 'failed',
            default => 'processing',
        };
    }
}
