<?php

namespace App\Workshops\Matching;

use App\Models\Workshop;
use App\Workshops\Support\Geo;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;

/**
 * The county of a place that did not state one, as an OpenStreetMap point usually does not.
 *
 * First from the locality, when every workshop the registry knows in a locality of that name is
 * in one county. Otherwise from the nearest county seat, which can be wrong near a border and is
 * labelled as the approximation it is.
 */
class LocalityCountyResolver
{
    /** @var array<string, string|null> */
    private array $byLocality = [];

    /** @return array{0: string|null, 1: string} [county code, how it was found] */
    public function resolve(?string $locality, ?float $latitude, ?float $longitude): array
    {
        $folded = TextNormalizer::fold($locality);

        if ($folded !== '') {
            if (! array_key_exists($folded, $this->byLocality)) {
                $counties = Workshop::query()->where('normalized_locality', $folded)->whereNotNull('county_code')->distinct()->limit(2)->pluck('county_code');
                $this->byLocality[$folded] = $counties->count() === 1 ? $counties->first() : null;
            }

            if ($this->byLocality[$folded] !== null) {
                return [$this->byLocality[$folded], 'locality'];
            }
        }

        if ($latitude !== null && $longitude !== null && Geo::inRomania($latitude, $longitude)) {
            return [RomanianCounties::nearestSeat($latitude, $longitude), 'nearest_county_seat'];
        }

        return [null, 'unknown'];
    }
}
