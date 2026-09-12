<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\Api\CatalogPublicationScope;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The assembly groups parts are filed under.
 *
 * A shop maps its own tree onto this one once, then filters every later parts query by
 * `category_id`. It is this catalogue's own taxonomy rather than licensed data, so the whole
 * active tree is published; `with_parts=1` narrows it to the groups that currently have
 * something visible in them.
 */
class CategoryTreeController extends CatalogApiController
{
    public function __invoke(Request $request, CatalogPublicationScope $scope): JsonResponse
    {
        return $this->cached($request, function () use ($request, $scope): array {
            $query = Category::query()->where('is_active', true)->orderBy('depth')->orderBy('position')->orderBy('name');

            if ($request->boolean('with_parts')) {
                $query->whereHas('catalogParts', fn ($parts) => $scope->visibleEntity($parts, 'catalog_part', 'catalog_parts.id'));
            }

            $categories = $query->get();
            $flat = $request->boolean('flat');

            $rows = $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'path' => $category->full_path,
                'depth' => (int) $category->depth,
                'parent_id' => $category->parent_id,
            ]);

            return [
                'data' => $flat ? $rows->values()->all() : self::nest($rows),
                'meta' => ['total' => $categories->count(), 'shape' => $flat ? 'flat' : 'tree'],
            ];
        });
    }

    /**
     * Nest by parent without a query per level. A row whose parent is not in the result — which
     * `with_parts=1` routinely causes, since an empty group is dropped while its children are
     * not — is promoted to the top instead of being lost with its parent.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function nest(Collection $rows): array
    {
        $present = $rows->pluck('id')->flip();
        $byParent = $rows->groupBy(fn (array $row) => $row['parent_id'] !== null && $present->has($row['parent_id'])
            ? $row['parent_id']
            : 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return $byParent->get($parentId, Collection::make())
                ->map(fn (array $row) => [...$row, 'children' => $build((int) $row['id'])])
                ->values()
                ->all();
        };

        return $build(0);
    }
}
