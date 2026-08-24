<?php

namespace App\Payments\Gateways;

use App\Models\Address;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\PaymentResult;
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
                    'amount' => (float) $order->grand_total,
                    'currency' => $order->currency,
                    'billing' => $this->address($order->billingAddress, $order),
                    'shipping' => $this->address($order->shippingAddress, $order),
                    'products' => $order->items->map(fn ($item): array => [
                        'name' => $item->name,
                        'code' => $item->sku ?? (string) $item->id,
                        'category' => 'Piese auto',
                        'price' => (float) $item->line_total,
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

    public function verifyWebhook(PaymentProvider $provider, string $payload, array $headers): bool
    {
        $apiKey = ($provider->credentials ?? [])['api_key'] ?? null;
        $authorization = $headers['authorization'][0] ?? $headers['Authorization'][0] ?? null;

        return $apiKey && $authorization && hash_equals($apiKey, $authorization);
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
