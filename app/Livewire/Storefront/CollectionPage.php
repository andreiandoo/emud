<?php

namespace App\Livewire\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\VehicleCollection;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything the shop carries for one car.
 *
 * The listing reads the pivot rather than re-deriving fitments per request: the matcher already
 * decided what belongs here at import time, and repeating that work on a page a visitor may
 * paginate through six times would be the same answer computed six times.
 */
#[Layout('layouts::storefront')]
class CollectionPage extends Component
{
    use WithPagination;

    public VehicleCollection $collection;

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: 'relevance')]
    public string $sort = 'relevance';

    public function mount(string $slug): void
    {
        $this->collection = VehicleCollection::query()
            ->with(['make', 'model'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher)
    {
        $vehicle = $context->current();

        return view('livewire.storefront.collection-page', [
            'vehicle' => $vehicle,
            'products' => $this->products(),
            'categories' => $this->availableCategories(),
            'brands' => $this->availableBrands(),
            'reviews' => $this->reviews(),
            'verdicts' => fn (Product $product) => $vehicle === null ? null : $matcher->verdictFor($product, $vehicle),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    /**
     * Built here rather than with @json in the view: the Blade directive takes its argument up
     * to the first balanced closing paren, which cuts a multi-line array literal in half and
     * leaves the compiled view a parse error — a 500 on every request, discovered only in
     * production.
     */
    private function breadcrumbs(): string
    {
        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => route('storefront.home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Colecții', 'item' => route('storefront.collections')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $this->collection->name, 'item' => $this->collection->url()],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function products(): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'media', 'variants'])
            ->whereHas('collections', fn (Builder $q) => $q->whereKey($this->collection->id));

        if ($this->category !== '') {
            $query->whereHas('categories', fn (Builder $q) => $q->where('categories.slug', $this->category));
        }

        if ($this->brand !== '') {
            $query->whereHas('brand', fn (Builder $q) => $q->where('slug', $this->brand));
        }

        $sorted = match ($this->sort) {
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('is_featured')->orderBy('id'),
        };

        return $sorted->paginate(24);
    }

    /**
     * Only the top-level categories that have something in this collection. A filter offering
     * "Frâne" for a car the shop stocks no brake parts for wastes the only row of chips there
     * is room for.
     *
     * @return EloquentCollection<int, Category>
     */
    private function availableCategories(): EloquentCollection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->active()
                ->whereHas('collections', fn (Builder $c) => $c->whereKey($this->collection->id)))
            ->orderBy('depth')
            ->orderBy('position')
            ->orderBy('name')
            ->limit(14)
            ->get();
    }

    /** @return EloquentCollection<int, Brand> */
    private function availableBrands(): EloquentCollection
    {
        return Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->active()
                ->whereHas('collections', fn (Builder $c) => $c->whereKey($this->collection->id)))
            ->orderBy('name')
            ->get();
    }

    /** @return EloquentCollection<int, Review> */
    private function reviews(): EloquentCollection
    {
        return Review::query()
            ->published()
            ->where(fn (Builder $q) => $q
                ->where('vehicle_collection_id', $this->collection->id)
                ->when($this->collection->model_id, fn (Builder $inner, int $modelId) => $inner->orWhere('model_id', $modelId)))
            ->ordered()
            ->limit(6)
            ->get();
    }
}
