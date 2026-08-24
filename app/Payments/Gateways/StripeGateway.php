<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\PaymentResult;
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
                'amount' => (int) round((float) $order->grand_total * 100),
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
        $secret = ($provider->credentials ?? [])['webhook_secret'] ?? null;
        $signature = $headers['stripe-signature'][0] ?? $headers['Stripe-Signature'][0] ?? null;

        if (! $secret || ! $signature) {
            return false;
        }

        $parts = collect(explode(',', $signature))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            return $key && $value ? [$key => $value] : [];
        });

        $timestamp = $parts->get('t');
        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $timestamp && abs(time() - (int) $timestamp) <= 300
            && hash_equals($expected, (string) $parts->get('v1'));
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
