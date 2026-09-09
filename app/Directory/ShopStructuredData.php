<?php

namespace App\Directory;

use App\Models\ServiceShop;

/**
 * The schema.org description of a workshop.
 *
 * Built as an array and tested, rather than assembled in the template, because the parts that
 * matter here are the ones that are easy to get quietly wrong: a price range with no currency,
 * an address with no country, opening hours in the wrong day format. None of those show up on
 * the page, so nothing but a test notices them.
 *
 * No aggregateRating is emitted. The site publishes no reviews, and a rating in structured data
 * that a reader cannot find on the page is exactly what search engines treat as deceptive.
 */
final class ShopStructuredData
{
    /** @return array<string, mixed> */
    public static function for(ServiceShop $shop): array
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'AutoRepair',
            'name' => $shop->name,
            'url' => $shop->url(),
            'description' => $shop->description ? trim(strip_tags($shop->description)) : null,
            'telephone' => $shop->phone ?: null,
            'email' => $shop->email ?: null,
            'image' => self::image($shop),
            'address' => self::address($shop),
            'geo' => self::geo($shop),
            'openingHoursSpecification' => $shop->schedule()->structuredData() ?: null,
            'makesOffer' => self::offers($shop),
            'areaServed' => $shop->city ?: null,
        ], fn (mixed $value) => $value !== null && $value !== []);

        return $data;
    }

    /** @return array<string, mixed> */
    private static function address(ServiceShop $shop): array
    {
        return array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $shop->address ?: null,
            'addressLocality' => $shop->city ?: null,
            'addressRegion' => $shop->county ?: null,
            'postalCode' => $shop->postal_code ?: null,
            // Stated rather than left out: a workshop with no country is placed by guesswork.
            'addressCountry' => 'RO',
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function geo(ServiceShop $shop): ?array
    {
        if ($shop->latitude === null || $shop->longitude === null) {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $shop->latitude,
            'longitude' => (float) $shop->longitude,
        ];
    }

    private static function image(ServiceShop $shop): ?string
    {
        $medium = $shop->relationLoaded('media') ? $shop->media->first() : $shop->media()->first();

        return $medium === null ? null : \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path);
    }

    /**
     * Only services with a stated price become offers. An offer with no price says nothing and
     * invites a search engine to invent one.
     *
     * @return list<array<string, mixed>>
     */
    private static function offers(ServiceShop $shop): array
    {
        $services = $shop->relationLoaded('services') ? $shop->services : $shop->services()->get();

        return $services
            ->filter(fn ($service) => $service->pivot->price_from !== null)
            ->map(fn ($service) => [
                '@type' => 'Offer',
                'itemOffered' => ['@type' => 'Service', 'name' => $service->name],
                'priceCurrency' => $service->pivot->currency ?: 'RON',
                'price' => (string) $service->pivot->price_from,
            ])
            ->values()
            ->all();
    }
}
