<?php

namespace App\Payments\Data;

readonly class PaymentResult
{
    public function __construct(
        public string $externalId,
        public string $status,
        public ?string $redirectUrl = null,
        public array $payload = [],
    ) {}
}
