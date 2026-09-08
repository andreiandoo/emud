<?php

namespace App\Payments\Gateways;

use App\Models\Address;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\PaymentResult;
use App\Payments\Support\JwtVerifier;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NetopiaGateway implements PaymentGateway
{
    public function start(PaymentProvider $provider, Order $order, array $context = []): PaymentResult
    {
        $credentials = $provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;
        $posSignature = $credentials['pos_signature'] ?? null;

        if (! $apiKey || ! $posSignature) {
            throw new RuntimeException('API key și POS signature NETOPIA nu sunt configurate.');
        }

        $order->loadMissing(['items', 'billingAddress', 'shippingAddress']);
        $endpoint = $provider->mode === 'live'
            ? 'https://secure.mobilpay.ro/pay/payment/card/start'
            : 'https://secure.sandbox.netopia-payments.com/payment/card/start';

        $response = Http::withHeaders(['Authorization' => $apiKey])
            ->post($endpoint, [
                'config' => [
                    'emailTemplate' => data_get($provider->settings, 'email_template', 'confirm'),
                    'notifyUrl' => $context['notify_url'] ?? route('payments.webhook', $provider->code),
                    'redirectUrl' => $context['return_url'] ?? url('/checkout/return'),
                    'language' => 'ro',
                ],
                'payment' => [
                    'options' => ['installments' => 1, 'bonus' => 0],
                    'instrument' => $context['instrument'] ?? ['type' => 'card', 'token' => ''],
                    'data' => $context['browser_data'] ?? [],
                ],
                'order' => [
                    'ntpID' => '',
                    'posSignature' => $posSignature,
                    'dateTime' => now()->toIso8601String(),
                    'description' => "Comanda {$order->number}",
                    'orderID' => $order->number,
                    'amount' => Money::of($order->grand_total, (string) $order->currency)->toDecimal(),
                    'currency' => $order->currency,
                    'billing' => $this->address($order->billingAddress, $order),
                    'shipping' => $this->address($order->shippingAddress, $order),
                    'products' => $order->items->map(fn ($item): array => [
                        'name' => $item->name,
                        'code' => $item->sku ?? (string) $item->id,
                        'category' => 'Piese auto',
                        'price' => Money::of($item->line_total, (string) $order->currency)->toDecimal(),
                        'vat' => (float) $item->tax_rate,
                    ])->all(),
                    'installments' => ['selected' => 1, 'available' => [0]],
                    'data' => ['order_id' => (string) $order->id],
                ],
            ])->throw()->json();

        $errorCode = data_get($response, 'error.code');
        $status = (string) data_get($response, 'payment.status', 'pending');

        return new PaymentResult(
            externalId: (string) data_get($response, 'payment.ntpID', $order->number),
            status: in_array($status, ['3', '4'], true) && $errorCode === '00' ? 'paid' : 'processing',
            redirectUrl: data_get($response, 'customerAction.url'),
            payload: $response,
        );
    }

    /**
     * The previous implementation compared the request's Authorization header against our own
     * API key. That authenticated nothing: the payload was never covered, and the API key is an
     * outbound request credential sent on every payment start, so anyone holding it could post
     * an arbitrary body and have a payment marked paid.
     *
     * IPN authenticity now rests on the signed token NETOPIA sends, verified against the POS
     * public key, with the body bound to the token through a payload hash claim. Verification
     * fails closed: without a configured public key no notification is accepted.
     *
     * The header and claim names are configurable because they must be confirmed against the
     * documentation of the specific merchant account before go-live; see docs/netopia-ipn.md.
     */
    public function verifyWebhook(PaymentProvider $provider, string $payload, array $headers): bool
    {
        $publicKey = (string) (($provider->credentials ?? [])['ipn_public_key'] ?? '');

        if (trim($publicKey) === '') {
            return false;
        }

        $settings = $provider->settings ?? [];
        $header = strtolower((string) ($settings['ipn_token_header'] ?? 'verification-token'));
        $token = $headers[$header][0] ?? null;

        if (! is_string($token) || $token === '') {
            return false;
        }

        $claims = JwtVerifier::verify($token, $publicKey, ['RS256', 'RS512']);

        if ($claims === null) {
            return false;
        }

        return $this->tokenIsFresh($claims, (int) ($settings['ipn_max_age_seconds'] ?? 300))
            && $this->tokenMatchesPayload($claims, $payload, $settings);
    }

    /**
     * Without this, a signed notification captured once could be replayed indefinitely.
     *
     * @param  array<string, mixed>  $claims
     */
    private function tokenIsFresh(array $claims, int $maxAge): bool
    {
        $expiry = $claims['exp'] ?? null;

        if ($expiry !== null && time() > (int) $expiry) {
            return false;
        }

        $issuedAt = $claims['iat'] ?? null;

        if ($issuedAt === null) {
            return $expiry !== null;
        }

        return abs(time() - (int) $issuedAt) <= max(1, $maxAge);
    }

    /**
     * A valid signature only proves NETOPIA issued the token, not that it describes this body.
     * The hash claim is what ties the two together, so a missing claim is a rejection rather
     * than a skipped check.
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $settings
     */
    private function tokenMatchesPayload(array $claims, string $payload, array $settings): bool
    {
        $claimName = (string) ($settings['ipn_payload_hash_claim'] ?? 'sub');
        $algorithm = (string) ($settings['ipn_payload_hash_algo'] ?? 'sha512');
        $claimed = $claims[$claimName] ?? null;

        if (! is_string($claimed) || $claimed === '' || ! in_array($algorithm, hash_algos(), true)) {
            return false;
        }

        return hash_equals(hash($algorithm, $payload), strtolower($claimed));
    }

    public function webhookReference(array $payload): ?string
    {
        return (string) (data_get($payload, 'payment.ntpID') ?: data_get($payload, 'order.ntpID')) ?: null;
    }

    public function webhookStatus(array $payload): string
    {
        $status = (string) data_get($payload, 'payment.status');

        return match ($status) {
            '3', '4' => 'paid',
            '5', '6', '7' => 'failed',
            default => 'processing',
        };
    }

    private function address(?Address $address, Order $order): array
    {
        return [
            'email' => $order->customer_email,
            'phone' => $address?->phone ?? $order->customer_phone ?? '',
            'firstName' => $address?->first_name ?? '',
            'lastName' => $address?->last_name ?? '',
            'city' => $address?->city ?? '',
            'country' => 642,
            'state' => $address?->county ?? '',
            'postalCode' => $address?->postal_code ?? '',
            'details' => trim(($address?->line_1 ?? '').' '.($address?->line_2 ?? '')),
        ];
    }
}
