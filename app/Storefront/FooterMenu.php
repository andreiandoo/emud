<?php

namespace App\Storefront;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The link columns in the footer, built from what the shop actually has.
 *
 * Hard-coded columns go stale the first time a page is renamed and nobody remembers the footer
 * exists. These read the same records the rest of the site does, so a page that stops being
 * published disappears from the footer on its own.
 *
 * Cached together rather than per column: the footer renders on every page of the shop, and
 * three separate cache entries would mean three round trips to warm instead of one.
 */
class FooterMenu
{
    private const CACHE_KEY = 'storefront.footer-menu';

    private const CACHE_TTL_SECONDS = 900;

    /** @return array{categories: Collection<int, Category>, pages: Collection<int, Page>} */
    public function columns(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('is_visible_in_menu', true)
                ->orderBy('position')
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'slug', 'full_path']),

            'pages' => Page::query()
                ->published()
                ->where('show_in_footer', true)
                ->orderBy('position')
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
