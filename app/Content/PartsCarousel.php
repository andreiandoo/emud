<?php

namespace App\Content;

use App\Models\Article;
use App\Models\ArticleBlock;
use App\Models\ArticleVehicle;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves a carousel block into products at render time.
 *
 * Deliberately not frozen into the article: a list baked in when the guide was written would
 * still be advertising parts that left the catalogue months ago. The trade-off is that a
 * carousel can come back empty, which the renderer has to handle rather than hide.
 *
 * A carousel can be filled three ways — an explicit product list, a category, or the vehicles
 * the article is about — and the vehicle case is why articles carry vehicle links at all.
 */
class PartsCarousel
{
    private const MAX_PRODUCTS = 12;

    /** @return Collection<int, Product> */
    public function resolve(ArticleBlock $block, Article $article): Collection
    {
        $limit = min(self::MAX_PRODUCTS, max(1, (int) $block->get('limit', 8)));
        $source = (string) $block->get('source', 'products');

        $query = Product::query()->active()->with(['brand', 'media', 'variants', 'fitments']);

        $query = match ($source) {
            'category' => $this->fromCategory($query, (int) $block->get('category_id')),
            'vehicle' => $this->fromArticleVehicles($query, $article),
            default => $this->fromExplicitList($query, (array) $block->get('product_ids', [])),
        };

        if ($query === null) {
            return new Collection;
        }

        return $query->orderByDesc('is_featured')->orderBy('id')->limit($limit)->get();
    }

    private function fromExplicitList(Builder $query, array $ids): ?Builder
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        return $ids === [] ? null : $query->whereIn('id', $ids);
    }

    private function fromCategory(Builder $query, int $categoryId): ?Builder
    {
        $category = Category::query()->find($categoryId);

        if ($category === null) {
            return null;
        }

        // The whole subtree, matching how a category page behaves: a carousel pointed at
        // "Suspensie" should show what is filed under it, not only what sits directly in it.
        $ids = Category::query()
            ->where('id', $category->id)
            ->orWhere('full_path', 'like', $category->full_path.'/%')
            ->pluck('id');

        return $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $ids));
    }

    /**
     * Parts that fit any vehicle the article is about. A guide linked to a model can therefore
     * carry a carousel that stays correct as fitment data improves, without the author
     * maintaining a product list by hand.
     */
    private function fromArticleVehicles(Builder $query, Article $article): ?Builder
    {
        $links = $article->relationLoaded('vehicles') ? $article->vehicles : $article->vehicles()->get();

        if ($links->isEmpty()) {
            return null;
        }

        return $query->where(function (Builder $outer) use ($links): void {
            $outer->where('is_universal', true);

            foreach ($links as $link) {
                $outer->orWhereHas('fitments', fn (Builder $fitment) => $this->constrain($fitment, $link));
            }
        });
    }

    private function constrain(Builder $fitment, ArticleVehicle $link): void
    {
        // Null on the link means "any", so it adds no constraint; null on the fitment row is the
        // wildcard the catalogue already uses.
        if ($link->make_id !== null) {
            $fitment->where(fn (Builder $q) => $q->whereNull('make_id')->orWhere('make_id', $link->make_id));
        }

        if ($link->model_id !== null) {
            $fitment->where(fn (Builder $q) => $q->whereNull('model_id')->orWhere('model_id', $link->model_id));
        }

        if ($link->generation_id !== null) {
            $fitment->where(fn (Builder $q) => $q->whereNull('generation_id')->orWhere('generation_id', $link->generation_id));
        }
    }
}
