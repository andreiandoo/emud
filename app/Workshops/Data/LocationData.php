<?php

namespace App\Workshops\Data;

use App\Workshops\Support\RomanianCounties;

/**
 * An address as a source gave it, plus a point and an honest statement of how good that point is.
 */
readonly class LocationData
{
    public function __construct(
        public ?string $address = null,
        public ?string $street = null,
        public ?string $streetNumber = null,
        public ?string $locality = null,
        public ?string $municipality = null,
        public ?string $countyCode = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        /** precise | approximate | registered_office | implausible | missing */
        public string $coordinateQuality = 'missing',
        public int $coordinateConfidence = 0,
        public ?string $rawCoordinates = null,
    ) {}

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function countyName(): ?string
    {
        return $this->countyCode === null ? null : RomanianCounties::name($this->countyCode);
    }
}
