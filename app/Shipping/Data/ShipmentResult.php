<?php

namespace App\Shipping\Data;

readonly class ShipmentResult
{
    public function __construct(
        public string $awbNumber,
        public string $status,
        public ?string $trackingUrl = null,
        public ?float $cost = null,
        public array $payload = [],
    ) {}
}
