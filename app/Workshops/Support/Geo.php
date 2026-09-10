<?php

namespace App\Workshops\Support;

class Geo
{
    private const EARTH_RADIUS_METERS = 6_371_000;

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float} */
    public static function boundingBox(float $lat, float $lng, float $radiusMeters): array
    {
        $latDelta = rad2deg($radiusMeters / self::EARTH_RADIUS_METERS);
        $lngDelta = rad2deg($radiusMeters / (self::EARTH_RADIUS_METERS * max(cos(deg2rad($lat)), 0.01)));

        return [
            'min_lat' => $lat - $latDelta,
            'max_lat' => $lat + $latDelta,
            'min_lng' => $lng - $lngDelta,
            'max_lng' => $lng + $lngDelta,
        ];
    }

    /**
     * "45.65,25.60" as RAR sends it. The number of decimals is kept because it is the only hint
     * of how precise the point is: two decimals is a town, not a gate.
     *
     * @return array{lat: float, lng: float, precision: int}|null
     */
    public static function parsePair(mixed $value): ?array
    {
        if (! is_string($value) || preg_match('/^\s*(-?\d{1,3}(?:\.(\d+))?)\s*[,;]\s*(-?\d{1,3}(?:\.(\d+))?)\s*$/', $value, $m) !== 1) {
            return null;
        }

        $lat = (float) $m[1];
        $lng = (float) $m[3];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat === 0.0 && $lng === 0.0)) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng, 'precision' => min(strlen($m[2] ?? ''), strlen($m[4] ?? ''))];
    }

    public static function inRomania(float $lat, float $lng): bool
    {
        return $lat >= 43.55 && $lat <= 48.30 && $lng >= 20.20 && $lng <= 29.80;
    }
}
