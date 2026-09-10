<?php

namespace App\Workshops\Contracts;

use App\Workshops\Geocoding\GeocodeQuery;
use App\Workshops\Geocoding\GeocodeResult;

/**
 * Turns an address into a point. Implementations must never invent one: no answer is null.
 */
interface Geocoder
{
    public function name(): string;

    /** False when nothing is configured; the pipeline then leaves addresses pending. */
    public function isConfigured(): bool;

    public function geocode(GeocodeQuery $query): ?GeocodeResult;
}
