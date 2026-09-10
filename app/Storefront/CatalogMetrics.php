<?php

namespace App\Storefront;

use App\Models\CatalogPart;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Cache;

/**
 * The four numbers that say how much of the catalogue there actually is.
 *
 * Cached for an hour. One of them counts models that have a real vehicle behind them, which is a
 * whereHas two levels deep over the whole vehicle graph — cheap once a day, absurd on every page
 * load. The numbers move slowly enough that an hour of staleness is invisible, and a number that
 * is off by a few parts is a far smaller problem than a page that takes two seconds to say it.
 */
class CatalogMetrics
{
    private const CACHE_KEY = 'storefront.catalog-metrics';

    private const CACHE_TTL_SECONDS = 3600;

    /** @return array<string, array{label: string, value: int}> */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => [
            'collections' => [
                'label' => 'colecții',
                'value' => VehicleCollection::query()->active()->count(),
            ],
            'models' => [
                'label' => 'modele de mașini',
                'value' => VehicleModel::query()->withConfigurations()->count(),
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

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
