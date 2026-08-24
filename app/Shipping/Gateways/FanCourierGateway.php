<?php

namespace App\Shipping\Gateways;

use App\Models\Order;
use App\Models\ShippingProvider;
use App\Shipping\Contracts\ShippingGateway;
use App\Shipping\Data\ShipmentResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FanCourierGateway implements ShippingGateway
{
    public function createAwb(ShippingProvider $provider, Order $order, array $parcel): ShipmentResult
    {
        $order->loadMissing(['shippingAddress', 'items']);
        $settings = $provider->settings ?? [];
        $response = $this->client($provider)->post(
            rtrim($settings['base_url'] ?? 'https://api.fancourier.ro', '/').($settings['awb_path'] ?? '/intern-awb'),
            [
                'clientId' => data_get($provider->credentials, 'client_id'),
                'shipments' => [[
                    'clientReference' => $order->number,
                    'service' => $settings['service'] ?? 'Standard',
                    'recipient' => [
                        'name' => trim($order->shippingAddress->first_name.' '.$order->shippingAddress->last_name),
                        'phone' => $order->shippingAddress->phone ?: $order->customer_phone,
                        'email' => $order->customer_email,
                        'county' => $order->shippingAddress->county,
                        'locality' => $order->shippingAddress->city,
                        'street' => $order->shippingAddress->line_1,
                        'postalCode' => $order->shippingAddress->postal_code,
                    ],
                    'parcels' => (int) ($parcel['pieces'] ?? 1),
                    'weight' => (float) ($parcel['weight_kg'] ?? 1),
                    'declaredValue' => (float) $order->grand_total,
                    'cashOnDelivery' => $order->payment_status === 'cod' ? (float) $order->grand_total : 0,
                    'contents' => $parcel['contents'] ?? "Piese auto · {$order->number}",
                ]],
            ]
        )->throw()->json();

        $awb = data_get($response, 'shipments.0.awbNumber') ?? data_get($response, 'data.0.awb') ?? data_get($response, 'awb');
        if (! $awb) {
            throw new RuntimeException('FAN Courier nu a returnat un număr AWB.');
        }

        return new ShipmentResult((string) $awb, 'created', "https://www.fancourier.ro/awb-tracking/?xawb={$awb}", data_get($response, 'shipments.0.cost'), $response);
    }

    public function track(ShippingProvider $provider, string $awbNumber): array
    {
        $settings = $provider->settings ?? [];

        return $this->client($provider)->get(
            rtrim($settings['base_url'] ?? 'https://api.fancourier.ro', '/').($settings['tracking_path'] ?? '/reports/awb'),
            ['awb' => $awbNumber]
        )->throw()->json();
    }

    private function client(ShippingProvider $provider): PendingRequest
    {
        $credentials = $provider->credentials ?? [];
        $token = $credentials['token'] ?? null;
        if (! $token) {
            throw new RuntimeException('Tokenul API FAN Courier nu este configurat.');
        }

        return Http::acceptJson()->withToken($token)->timeout(30)->retry(2, 300);
    }
}
