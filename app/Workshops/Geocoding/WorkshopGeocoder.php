<?php

namespace App\Workshops\Geocoding;

use App\Models\Workshop;
use App\Workshops\Contracts\Geocoder;
use App\Workshops\Support\RomanianCounties;

/**
 * Places workshops that have an address but no trustworthy point, a bounded batch at a time.
 *
 * An answer is only used when it is better than what the workshop already has and lies in the
 * workshop's own county; a geocoder that returns a same-named street across the country is
 * wrong, not approximate.
 */
class WorkshopGeocoder
{
    public function __construct(private Geocoder $geocoder) {}

    public function isConfigured(): bool
    {
        return $this->geocoder->isConfigured();
    }

    /** @return array{attempted: int, located: int, approximate: int, failed: int} */
    public function run(int $limit, ?string $countyCode = null): array
    {
        $counts = ['attempted' => 0, 'located' => 0, 'approximate' => 0, 'failed' => 0];

        if (! $this->geocoder->isConfigured()) {
            return $counts;
        }

        $limit = max(1, min($limit, (int) config('workshops.geocoder.max_per_run')));

        $workshops = Workshop::query()
            ->canonical()
            ->where('is_active', true)
            ->whereIn('geocode_status', ['pending', 'approximate'])
            ->whereNotNull('locality')
            ->where(fn ($query) => $query->whereNull('coordinates_confidence')->orWhere('coordinates_confidence', '<', 45))
            ->when($countyCode, fn ($query, string $code) => $query->where('county_code', $code))
            ->orderByRaw('case when geocode_status = ? then 0 else 1 end', ['pending'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($workshops as $workshop) {
            $counts['attempted']++;
            $result = $this->geocoder->geocode(GeocodeQuery::forWorkshop($workshop));

            if ($result === null || ($workshop->county_code !== null && ! RomanianCounties::isPlausible($workshop->county_code, $result->latitude, $result->longitude))) {
                $counts['failed']++;

                if ($workshop->geocode_status === 'pending') {
                    $workshop->forceFill(['geocode_status' => 'failed'])->save();
                }

                continue;
            }

            if ($result->confidence <= (int) $workshop->coordinates_confidence) {
                $counts['failed']++;

                continue;
            }

            $workshop->forceFill([
                'latitude' => $result->latitude,
                'longitude' => $result->longitude,
                'coordinates_source' => 'geocoder:'.$result->provider,
                'coordinates_confidence' => $result->confidence,
                'geocode_status' => $result->precision === 'street' ? 'located' : 'approximate',
            ])->save();

            $counts[$result->precision === 'street' ? 'located' : 'approximate']++;
        }

        return $counts;
    }
}
