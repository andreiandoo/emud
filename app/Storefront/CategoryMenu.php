<?php

namespace App\Storefront;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The parts catalogue as one pyramidal menu: top level, groups beneath each, links beneath
 * those.
 *
 * Built from a single query and assembled in memory. Walking the tree with a query per level
 * would cost one round trip per branch on every page load, which is the usual reason a mega
 * menu ends up cached badly or trimmed to two levels.
 */
class CategoryMenu
{
    private const CACHE_KEY = 'storefront.category-menu';

    private const CACHE_TTL_SECONDS = 900;

    /** @return Collection<int, array<string, mixed>> */
    public function tree(int $depth = 3): Collection
    {
        return Cache::remember(
            self::CACHE_KEY.":{$depth}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->build($depth),
        );
    }

    /**
     * Called when the taxonomy changes. The menu is on every page, so a stale one is visible
     * everywhere at once.
     */
    public static function forget(): void
    {
        foreach (range(1, 5) as $depth) {
            Cache::forget(self::CACHE_KEY.":{$depth}");
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    private function build(int $depth): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->where('is_visible_in_menu', true)
            ->where('depth', '<', $depth)
            ->orderBy('depth')
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'full_path', 'depth', 'icon']);

        $byParent = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        return $this->nest($byParent, 0);
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $byParent
     * @return Collection<int, array<string, mixed>>
     */
    private function nest(Collection $byParent, int $parentId): Collection
    {
        return ($byParent[$parentId] ?? collect())->map(fn (Category $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'path' => $category->full_path,
            'icon' => $category->icon,
            'children' => $this->nest($byParent, $category->id),
        ])->values();
    }
}
