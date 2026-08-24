<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Models\PaymentProvider;
use App\Payments\Data\PaymentResult;

interface PaymentGateway
{
    public function start(PaymentProvider $provider, Order $order, array $context = []): PaymentResult;

    public function verifyWebhook(PaymentProvider $provider, string $payload, array $headers): bool;

    public function webhookReference(array $payload): ?string;

    public function webhookStatus(array $payload): string;
}
