<?php

namespace App\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingProvider;
use Illuminate\Support\Str;

class ShipmentService
{
    public function __construct(private ShippingGatewayRegistry $registry) {}

    public function createAwb(Order $order, ShippingProvider $provider, array $parcel): Shipment
    {
        abort_unless($provider->is_active, 422, 'Curierul este inactiv.');
        $result = $this->registry->gateway($provider)->createAwb($provider, $order, $parcel);

        $shipment = Shipment::create([
            'uuid' => Str::uuid(), 'order_id' => $order->id, 'shipping_provider_id' => $provider->id,
            'awb_number' => $result->awbNumber, 'status' => $result->status,
            'tracking_url' => $result->trackingUrl, 'cost' => $result->cost,
            'currency' => $order->currency, 'payload' => $result->payload,
        ]);
        $order->update(['fulfillment_status' => 'processing']);

        return $shipment;
    }

    public function refreshTracking(Shipment $shipment): Shipment
    {
        $events = $this->registry->gateway($shipment->provider)->track($shipment->provider, $shipment->awb_number);
        foreach (data_get($events, 'events', []) as $event) {
            $shipment->events()->updateOrCreate(['external_id' => $event['id'] ?? null], [
                'status' => $event['status'] ?? 'unknown', 'description' => $event['description'] ?? null,
                'location' => $event['location'] ?? null, 'occurred_at' => $event['date'] ?? now(), 'payload' => $event,
            ]);
        }

        return $shipment->refresh();
    }
}
