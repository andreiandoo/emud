<?php

namespace App\Payments;

use App\Models\PaymentProvider;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\NetopiaGateway;
use App\Payments\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayRegistry
{
    public function gateway(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider->driver) {
            'stripe' => app(StripeGateway::class),
            'netopia' => app(NetopiaGateway::class),
            default => throw new InvalidArgumentException("Procesator de plăți necunoscut: {$provider->driver}"),
        };
    }

    public function defaultProvider(): PaymentProvider
    {
        return PaymentProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->firstOrFail();
    }
}
