<?php

namespace App\Workshops\Sources\Osm;

use App\Workshops\Data\LocationData;
use App\Workshops\Support\PhoneNormalizer;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;

/**
 * Reads the payload the OSM importer stores (type, id, a centre point, every tag) into a POI.
 *
 * Services come only from explicit tags: shop=tyres says tyres, service:vehicle:brakes=yes says
 * brakes. What a mapper wrote is recorded as "OSM tagged", never as an authorisation.
 */
class OsmPoiParser
{
    /** @var array<string, array<string, int>> "key=value" => [service key => confidence] */
    private const PRIMARY_TAGS = [
        'shop=car_repair' => ['general_repair' => 60],
        'craft=car_repair' => ['general_repair' => 60],
        'shop=tyres' => ['tyres' => 80],
        'shop=truck_repair' => ['truck_service' => 80],
        'amenity=vehicle_inspection' => ['itp' => 80],
    ];

    /** @var array<string, array<string, int>> service:vehicle:* suffix => [service key => confidence] */
    private const VEHICLE_SERVICES = [
        'car_repair' => ['general_repair' => 65],
        'repairs' => ['general_repair' => 60],
        'tyres' => ['tyres' => 75],
        'brakes' => ['brakes' => 75],
        'transmission' => ['transmission' => 75],
        'clutch' => ['transmission' => 65],
        'engine' => ['engine' => 70],
        'engine_repair' => ['engine' => 70],
        'diagnostics' => ['diagnostics' => 75],
        'electrical' => ['electrical' => 75],
        'batteries' => ['electrical' => 60],
        'body_repair' => ['bodywork' => 75],
        'painting' => ['painting' => 75],
        'glass' => ['glass' => 75],
        'air_conditioning' => ['air_conditioning' => 75],
        'wheel_alignment' => ['wheel_alignment' => 75],
        'alignment' => ['wheel_alignment' => 70],
        'oil_change' => ['maintenance' => 70],
        'inspection' => ['maintenance' => 50],
        'exhaust' => ['exhaust' => 70],
        'suspension' => ['suspension' => 70],
        'truck_repair' => ['truck_service' => 75],
        'motorcycle_repair' => ['motorcycle_service' => 70],
        'lpg' => ['gpl' => 75],
        'cng' => ['gnc' => 75],
        'towing' => ['towing' => 70],
        'detailing' => ['detailing' => 70],
        'electric_vehicles' => ['electric_vehicle' => 70],
        'electric' => ['electric_vehicle' => 65],
        'hybrid' => ['hybrid' => 65],
        '4x4' => ['4x4_drivetrain' => 65],
        '4wd' => ['4x4_drivetrain' => 65],
        'offroad' => ['offroad_modifications' => 60],
        'off_road' => ['offroad_modifications' => 60],
    ];

    public function parse(array $payload): OsmPoi
    {
        $tags = array_map(fn (mixed $value): string => (string) $value, array_filter((array) ($payload['tags'] ?? []), 'is_scalar'));
        $tag = fn (string ...$keys): ?string => $this->first($tags, $keys);

        $locality = TextNormalizer::titleIfShouting($tag('addr:city', 'addr:town', 'addr:village', 'addr:place', 'addr:suburb'));
        $street = $tag('addr:street');
        $number = $tag('addr:housenumber');
        $county = RomanianCounties::resolve($tag('addr:county', 'addr:state', 'is_in:county'));

        $address = $this->join([
            $street !== null ? trim($street.($number !== null ? ' '.$number : '')) : ($tag('addr:place') ?? null),
            $locality,
            $county !== null ? 'jud. '.RomanianCounties::name($county) : null,
        ]);

        $latitude = isset($payload['lat']) && is_numeric($payload['lat']) ? (float) $payload['lat'] : null;
        $longitude = isset($payload['lng']) && is_numeric($payload['lng']) ? (float) $payload['lng'] : null;

        return new OsmPoi(
            osmType: (string) ($payload['type'] ?? 'node'),
            osmId: (int) ($payload['id'] ?? 0),
            name: $tag('name', 'name:ro', 'official_name', 'brand', 'operator'),
            latitude: $latitude,
            longitude: $longitude,
            isArea: (bool) ($payload['area'] ?? false),
            tags: $tags,
            location: new LocationData(
                address: $address,
                street: $street,
                streetNumber: $number,
                locality: $locality,
                countyCode: $county,
                postalCode: preg_match('/^\d{6}$/', (string) $tag('addr:postcode')) === 1 ? $tag('addr:postcode') : null,
                latitude: $latitude,
                longitude: $longitude,
                coordinateQuality: $latitude !== null ? 'precise' : 'missing',
                coordinateConfidence: $latitude === null ? 0 : (($payload['area'] ?? false) ? 75 : 85),
            ),
            phones: PhoneNormalizer::extractAll(implode(';', array_filter([$tag('phone'), $tag('contact:phone'), $tag('mobile'), $tag('contact:mobile')]))),
            emails: $this->emails([$tag('email'), $tag('contact:email')]),
            websites: $this->urls([$tag('website'), $tag('contact:website'), $tag('url')]),
            facebook: $this->social($tag('contact:facebook', 'facebook'), 'facebook.com'),
            instagram: $this->social($tag('contact:instagram', 'instagram'), 'instagram.com'),
            openingHours: $tag('opening_hours'),
            operator: $tag('operator'),
            brand: $tag('brand'),
            services: $this->services($tags),
        );
    }

    /** @return array<string, int> */
    private function services(array $tags): array
    {
        $services = [];

        foreach ($tags as $key => $value) {
            $found = self::PRIMARY_TAGS["{$key}={$value}"] ?? [];

            if (str_starts_with($key, 'service:vehicle:') && in_array(strtolower($value), ['yes', 'only'], true)) {
                $found = self::VEHICLE_SERVICES[substr($key, strlen('service:vehicle:'))] ?? [];
            }

            foreach ($found as $service => $confidence) {
                $services[$service] = max($services[$service] ?? 0, $confidence);
            }
        }

        return $services;
    }

    private function first(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (($value = TextNormalizer::clean($tags[$key] ?? null)) !== null) {
                return $value;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function emails(array $values): array
    {
        $emails = [];

        foreach ($values as $value) {
            foreach (preg_split('/\s*[;,]\s*/', (string) $value) ?: [] as $email) {
                $email = mb_strtolower(trim($email));

                if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[$email] = true;
                }
            }
        }

        return array_keys($emails);
    }

    /** @return list<string> */
    private function urls(array $values): array
    {
        $urls = [];

        foreach ($values as $value) {
            foreach (preg_split('/\s*;\s*/', (string) $value) ?: [] as $url) {
                $url = trim($url);

                if ($url === '' || ! str_contains($url, '.')) {
                    continue;
                }

                $urls[preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$url] = true;
            }
        }

        return array_keys($urls);
    }

    /** OSM holds either a full URL or just the page name. */
    private function social(?string $value, string $domain): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        return 'https://www.'.$domain.'/'.ltrim($value, '@/');
    }

    private function join(array $parts): ?string
    {
        $parts = array_filter($parts, fn (mixed $part): bool => is_string($part) && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
