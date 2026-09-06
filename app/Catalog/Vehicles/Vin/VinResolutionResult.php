<?php

namespace App\Catalog\Vehicles\Vin;

final readonly class VinResolutionResult
{
    /** @param array<string, mixed> $decoded @param array<int, array<string, mixed>> $candidates */
    public function __construct(
        public string $status,
        public ?int $vehicleConfigurationId,
        public float $confidence,
        public array $decoded = [],
        public array $candidates = [],
        public array $missing = [],
        public ?string $message = null,
    ) {}
}
