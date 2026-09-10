<?php

namespace App\Storefront;

use App\Models\VehicleCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The collections the shop puts in front of a visitor.
 *
 * Cached because it renders in the "Choose your ride" strip on the home page and again in the
 * footer of every page, and because the fallback below counts products across a pivot — cheap
 * once, wasteful on every request.
 */
class CollectionShowcase
{
    private const CACHE_KEY = 'storefront.collections.featured';

    private const CACHE_TTL_SECONDS = 900;

    /** @return Collection<int, VehicleCollection> */
    public function featured(int $limit = 12): Collection
    {
        return Cache::remember(
            self::CACHE_KEY.":{$limit}",
            self::CACHE_TTL_SECONDS,
            fn (): Collection => $this->build($limit),
        );
    }

    public static function forget(): void
    {
        foreach ([6, 8, 10, 12, 16, 24] as $limit) {
            Cache::forget(self::CACHE_KEY.":{$limit}");
        }
    }

    /**
     * Whatever an operator marked as featured, and if they marked nothing, the collections with
     * the most parts behind them. A strip that is empty on a fresh install reads as a broken
     * page rather than as an unconfigured one.
     *
     * @return Collection<int, VehicleCollection>
     */
    private function build(int $limit): Collection
    {
        $featured = VehicleCollection::query()->active()->featured()->ordered()->limit($limit)->get();

        if ($featured->isNotEmpty()) {
            return $featured;
        }

        return VehicleCollection::query()
            ->active()
            ->withCount('products')
            // whereHas rather than a HAVING on the alias: PostgreSQL does not let HAVING refer
            // to a select alias, so the obvious spelling raises instead of filtering.
            ->whereHas('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
