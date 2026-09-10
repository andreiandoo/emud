<?php

namespace App\Workshops\Geocoding;

use App\Workshops\Contracts\Geocoder;

/** The default: no geocoder is configured, so every address stays pending. */
class NullGeocoder implements Geocoder
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function geocode(GeocodeQuery $query): ?GeocodeResult
    {
        return null;
    }
}
