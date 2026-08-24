<?php

namespace App\Shipping\Contracts;

use App\Models\Order;
use App\Models\ShippingProvider;
use App\Shipping\Data\ShipmentResult;

interface ShippingGateway
{
    public function createAwb(ShippingProvider $provider, Order $order, array $parcel): ShipmentResult;

    public function track(ShippingProvider $provider, string $awbNumber): array;
}
