<?php

namespace App\Catalog\Vehicles\Vin;

final readonly class VinResolutionResult
{
    /**
     * makeId, modelId and generationId are the catalogue's, set when a VIN names the car only as
     * far as its make or its model: enough to fill in part of the choice, not to pick a build.
     *
     * @param  array<string, mixed>  $decoded
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function __construct(
        public string $status,
        public ?int $vehicleConfigurationId,
        public float $confidence,
        public array $decoded = [],
        public array $candidates = [],
        public array $missing = [],
        public ?string $message = null,
        public ?int $makeId = null,
        public ?int $modelId = null,
        public ?int $generationId = null,
    ) {}
}
