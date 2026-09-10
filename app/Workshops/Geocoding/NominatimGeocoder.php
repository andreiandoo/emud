<?php

namespace App\Workshops\Geocoding;

use App\Workshops\Contracts\Geocoder;
use App\Workshops\Ingestion\SourceHttpClient;
use App\Workshops\Ingestion\SourceThrottle;
use Throwable;

/**
 * A Nominatim instance, meant to be our own (self-hosted on the Romania extract).
 *
 * The public nominatim.openstreetmap.org forbids bulk geocoding in its usage policy, so it is
 * refused unless WORKSHOPS_ALLOW_PUBLIC_NOMINATIM is switched on for a small, supervised batch,
 * and even then requests stay one per second.
 */
class NominatimGeocoder implements Geocoder
{
    private const PUBLIC_HOSTS = ['nominatim.openstreetmap.org'];

    public function __construct(private SourceHttpClient $http, private SourceThrottle $throttle) {}

    public function name(): string
    {
        return 'nominatim';
    }

    public function isConfigured(): bool
    {
        $url = (string) config('workshops.geocoder.nominatim_url');

        if ($url === '') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ! in_array($host, self::PUBLIC_HOSTS, true) || (bool) config('workshops.geocoder.allow_public_nominatim');
    }

    public function geocode(GeocodeQuery $query): ?GeocodeResult
    {
        if (! $this->isConfigured() || $query->locality === null) {
            return null;
        }

        $this->throttle->wait('geocoder', max(1000, (int) config('workshops.geocoder.request_delay_ms')));

        try {
            $hits = $this->http->request(30, 2, 2000)
                ->acceptJson()
                ->get(rtrim((string) config('workshops.geocoder.nominatim_url'), '/').'/search', array_filter([
                    'street' => trim(($query->number ?? '').' '.($query->street ?? '')) ?: null,
                    'city' => $query->locality,
                    'county' => $query->county,
                    'postalcode' => $query->postalCode,
                    'country' => 'Romania',
                    'countrycodes' => 'ro',
                    'format' => 'jsonv2',
                    'limit' => 1,
                ]))
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        $hit = is_array($hits) ? ($hits[0] ?? null) : null;

        if (! is_array($hit) || ! is_numeric($hit['lat'] ?? null) || ! is_numeric($hit['lon'] ?? null)) {
            return null;
        }

        // place_rank 26 and above is a street or a building; 16–25 a town or a quarter.
        $rank = (int) ($hit['place_rank'] ?? 0);

        if ($rank < 16) {
            return null;
        }

        $street = $rank >= 26;

        return new GeocodeResult((float) $hit['lat'], (float) $hit['lon'], $street ? 'street' : 'locality', $street ? 70 : 30, $this->name(), $hit);
    }
}
