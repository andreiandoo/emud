<?php

namespace App\Storefront;

use App\Models\CatalogPart;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Cache;

/**
 * The numbers that say how much of the catalogue there actually is.
 *
 * Two things they have to get right. First, they must add up in the reader's head: collections
 * are one per make plus one per model, so a page showing 11.764 collections next to 10.901
 * models and nothing else invites the reasonable question of how there can be more collections
 * than cars. The makes are therefore shown too, and 863 + 10.901 stops being a puzzle.
 *
 * Second, they must count what the sources actually produced. EEA, vPIC and lifeofcapo are
 * vehicle catalogues: they fill makes, models, generations, engines and configurations. None of
 * them carries a single part number, so catalog_parts stays at zero until a parts catalogue is
 * connected — and a zero is dropped rather than printed.
 */
class CatalogMetrics
{
    private const CACHE_KEY = 'storefront.catalog-metrics';

    /** Fresh for an hour, still served for a day while it refreshes behind the response. */
    private const TTL = [3600, 86400];

    /** @return array<string, array{label: string, value: int}> */
    public function snapshot(): array
    {
        // flexible(), not remember(): the make and model counts are EXISTS chains three levels
        // deep across the whole vehicle graph, and with a plain TTL the unlucky visitor whose
        // request happens to land the moment it expires pays for the recount. This way only the
        // very first request ever waits; every later one gets the old numbers and the new ones
        // are computed after the response has gone out.
        return Cache::flexible(self::CACHE_KEY, self::TTL, fn (): array => [
            'collections' => [
                'label' => 'colecții',
                'value' => VehicleCollection::query()->active()->count(),
            ],
            'makes' => [
                'label' => 'mărci',
                'value' => VehicleMake::query()->withConfigurations()->count(),
            ],
            'models' => [
                'label' => 'modele de mașini',
                'value' => VehicleModel::query()->withConfigurations()->count(),
            ],
            'configurations' => [
                'label' => 'motorizări identificate',
                'value' => VehicleConfiguration::query()->count(),
            ],
            'parts' => [
                'label' => 'repere în catalogul tehnic',
                'value' => CatalogPart::query()->count(),
            ],
            'products' => [
                'label' => 'produse în magazin',
                'value' => Product::query()->active()->count(),
            ],
        ]);
    }

    /** Both keys: flexible() records when it last recomputed in a companion entry of its own. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('illuminate:cache:flexible:created:'.self::CACHE_KEY);
    }
}
