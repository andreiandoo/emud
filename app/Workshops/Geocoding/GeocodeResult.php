<?php

namespace App\Workshops\Geocoding;

readonly class GeocodeResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        /** street | locality */
        public string $precision,
        public int $confidence,
        public string $provider,
        public array $raw = [],
    ) {}
}
