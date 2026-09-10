<?php

namespace App\Workshops\Geocoding;

use App\Models\Workshop;

readonly class GeocodeQuery
{
    public function __construct(
        public ?string $street,
        public ?string $number,
        public ?string $locality,
        public ?string $county,
        public ?string $postalCode = null,
    ) {}

    public static function forWorkshop(Workshop $workshop): self
    {
        return new self($workshop->street, $workshop->street_number, $workshop->locality, $workshop->county, $workshop->postal_code);
    }
}
