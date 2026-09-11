<?php

namespace App\Directory;

use App\Models\ServiceShop;
use App\Models\Workshop;
use App\Workshops\Support\RomanianCounties;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Counties, and the towns in each, for the address dropdowns.
 *
 * The towns are the ones there are workshops in — the registry and the directory — which covers
 * where people drive from without shipping a national gazetteer. A town missing from the list
 * can still be typed: the list suggests, it never refuses an address.
 */
class Localities
{
    /** @return list<string> the 41 counties and Bucharest, in alphabetical order */
    public static function counties(): array
    {
        $names = array_values(array_map(fn (array $county): string => $county[0], RomanianCounties::ALL));

        usort($names, fn (string $a, string $b): int => strcmp(Str::ascii($a), Str::ascii($b)));

        return $names;
    }

    /** @return list<string> the towns known in a county, its seat first */
    public static function forCounty(?string $county): array
    {
        $code = RomanianCounties::resolve($county);

        if ($code === null) {
            return [];
        }

        return Cache::remember('directory:localities:'.$code, now()->addDay(), function () use ($code): array {
            $name = (string) RomanianCounties::name($code);
            $seat = RomanianCounties::ALL[$code][1];

            $towns = ServiceShop::query()->where('county', $name)->distinct()->pluck('city')
                ->merge(Workshop::query()->where('county_code', $code)->whereNotNull('locality')->distinct()->pluck('locality'))
                ->map(fn (mixed $town): string => WorkshopListingSync::tidy((string) $town))
                ->filter()
                ->push($seat)
                ->unique(fn (string $town): string => Str::lower(Str::ascii($town)))
                ->sortBy(fn (string $town): string => (Str::ascii($town) === Str::ascii($seat) ? '0' : '1').Str::lower(Str::ascii($town)))
                ->values()
                ->all();

            return $towns;
        });
    }
}
