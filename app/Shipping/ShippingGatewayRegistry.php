<?php

namespace App\Shipping;

use App\Models\ShippingProvider;
use App\Shipping\Contracts\ShippingGateway;
use App\Shipping\Gateways\FanCourierGateway;
use InvalidArgumentException;

class ShippingGatewayRegistry
{
    public function gateway(ShippingProvider $provider): ShippingGateway
    {
        return match ($provider->driver) {
            'fan_courier' => app(FanCourierGateway::class),
            default => throw new InvalidArgumentException("Curier necunoscut: {$provider->driver}"),
        };
    }
}
