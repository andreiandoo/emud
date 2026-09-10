<?php

namespace App\Livewire\Storefront;

use App\Enums\StockStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\VehicleCollection;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything the shop carries for one car.
 *
 * The listing reads the pivot rather than re-deriving fitments per request: the matcher already
 * decided what belongs here at import time, and repeating that work on a page a visitor may
 * paginate through six times would be the same answer computed six times.
 *
 * Laid out full width, so the wide image can run edge to edge behind the title the way the
 * reference does; every other section wraps itself in .shell.
 */
#[Layout('layouts::storefront', ['fullWidth' => true])]
class CollectionPage extends Component
{
    use WithPagination;

    public VehicleCollection $collection;

    /** @var EloquentCollection<int, VehicleCollection>|null */
    private ?EloquentCollection $children = null;

    /** @var list<string> */
    #[Url(as: 'cat', except: [])]
    public array $categories = [];

    /** @var list<string> */
    #[Url(as: 'brand', except: [])]
    public array $brands = [];

    /** Slugs of the derivatives to narrow to, on a main collection's page. @var list<string> */
    #[Url(as: 'varianta', except: [])]
    public array $variants = [];

    #[Url(as: 'min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'max', except: '')]
    public string $priceMax = '';

    #[Url(as: 'stoc', except: false)]
    public bool $inStock = false;

    #[Url(as: 'potrivite', except: false)]
    public bool $fitsMyVehicle = false;

    #[Url(except: 'relevance')]
    public string $sort = 'relevance';

    public function mount(string $slug): void
    {
        $this->collection = VehicleCollection::query()
            ->with(['make', 'model', 'parent'])
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

    #[On('vehicle-changed')]
    public function vehicleChanged(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['categories', 'brands', 'variants', 'priceMin', 'priceMax', 'inStock', 'fitsMyVehicle']);
        $this->resetPage();
    }

    /** Removing one filter from the summary row, without opening the panel it came from. */
    public function removeFilter(string $type, string $value = ''): void
    {
        match ($type) {
            'category' => $this->categories = array_values(array_diff($this->categories, [$value])),
            'brand' => $this->brands = array_values(array_diff($this->brands, [$value])),
            'variant' => $this->variants = array_values(array_diff($this->variants, [$value])),
            'price' => $this->reset(['priceMin', 'priceMax']),
            'stock' => $this->inStock = false,
            'vehicle' => $this->fitsMyVehicle = false,
            default => null,
        };

        $this->resetPage();
    }

    public function activeFilterCount(): int
    {
        return count($this->categories)
            + count($this->brands)
            + count($this->variants)
            + (($this->priceMin !== '' || $this->priceMax !== '') ? 1 : 0)
            + ($this->inStock ? 1 : 0)
            + ($this->fitsMyVehicle ? 1 : 0);
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher)
    {
        $vehicle = $context->current();

        return view('livewire.storefront.collection-page', [
            'vehicle' => $vehicle,
            'products' => $this->products($vehicle, $matcher),
            'categoryFacets' => $this->categoryFacets(),
            'brandFacets' => $this->brandFacets(),
            'variantFacets' => $this->variantFacets(),
            'priceBounds' => $this->priceBounds(),
            'children' => $this->children(),
            'reviews' => $this->reviews(),
            'verdicts' => fn (Product $product) => $vehicle === null ? null : $matcher->verdictFor($product, $vehicle),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    /**
     * The derivatives filed under this one — the 35C16 and 35S18 beneath an Iveco.
     *
     * Only on a main collection: a derivative has none by construction, and asking for them is a
     * query that can only ever come back empty.
     *
     * @return EloquentCollection<int, VehicleCollection>
     */
    private function children(): EloquentCollection
    {
        if ($this->collection->parent_id !== null) {
            return new EloquentCollection;
        }

        // Memoised: the chooser, the filter facets and the hero count all ask for the same list
        // inside one render.
        return $this->children ??= $this->collection->children()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'square_image_path', 'year_from', 'year_to']);
    }

    private function products(mixed $vehicle, FitmentMatcher $matcher): LengthAwarePaginator
    {
        $query = $this->filtered();

        if ($vehicle !== null && $this->fitsMyVehicle) {
            $matcher->scopeForVehicle($query, $vehicle);
        }

        return $this->sorted($query)->paginate(24);
    }

    /** Everything in the collection, with every filter applied except the ones named. */
    private function filtered(string ...$except): Builder
    {
        $skip = array_flip($except);

        $query = Product::query()
            ->active()
            ->with(['brand', 'media', 'variants'])
            ->whereHas('collections', fn (Builder $q) => $q->whereKey($this->collection->id));

        if (! isset($skip['categories']) && $this->categories !== []) {
            $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.slug', $this->categories));
        }

        if (! isset($skip['brands']) && $this->brands !== []) {
            $query->whereHas('brand', fn (Builder $q) => $q->whereIn('slug', $this->brands));
        }

        // A second EXISTS against the same pivot, not a narrowing of the first: the product must
        // be in this collection *and* in one of the chosen derivatives.
        if (! isset($skip['variants']) && $this->variants !== []) {
            $query->whereHas('collections', fn (Builder $q) => $q->whereIn('vehicle_collections.slug', $this->variants));
        }

        if (! isset($skip['price']) && ($this->priceMin !== '' || $this->priceMax !== '')) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->where('is_active', true)->whereNotNull('retail_price');

                if ($this->priceMin !== '') {
                    $q->where('retail_price', '>=', (float) $this->priceMin);
                }

                if ($this->priceMax !== '') {
                    $q->where('retail_price', '<=', (float) $this->priceMax);
                }
            });
        }

        if (! isset($skip['stock']) && $this->inStock) {
            // Stock lives on supplier offers, two relations away. "Available" means any supplier
            // has it now — backorder is not the same promise, so it is excluded here.
            $query->whereHas('supplierProducts', fn (Builder $q) => $q->whereHas('offer', fn (Builder $o) => $o
                ->where('is_active', true)
                ->whereIn('stock_status', [StockStatus::InStock->value, StockStatus::LowStock->value])));
        }

        return $query;
    }

    private function sorted(Builder $query): Builder
    {
        // Price sorting reads the cheapest active variant through a correlated subquery rather
        // than a join: a product with three variants would otherwise appear three times.
        $cheapest = ProductVariant::query()
            ->select('retail_price')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true)
            ->orderBy('retail_price')
            ->limit(1);

        return match ($this->sort) {
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('published_at'),
            'price-asc' => $query->orderBy($cheapest),
            'price-desc' => $query->orderByDesc($cheapest),
            default => $query->orderByDesc('is_featured')->orderBy('id'),
        };
    }

    /**
     * How many products each category would leave, with the other filters still applied.
     *
     * Counted without the category filter itself, which is what makes a facet list usable: with
     * it applied, every unticked box would read zero and the customer could only ever narrow.
     *
     * @return Collection<int, array{slug: string, name: string, total: int}>
     */
    private function categoryFacets(): Collection
    {
        $ids = $this->filtered('categories')->select('products.id');

        return Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->whereIn('products.id', $ids))
            ->withCount(['products' => fn (Builder $q) => $q->whereIn('products.id', $ids)])
            ->orderBy('depth')
            ->orderBy('position')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Category $category): array => [
                'slug' => (string) $category->slug,
                'name' => (string) $category->name,
                'total' => (int) $category->products_count,
            ]);
    }

    /** @return Collection<int, array{slug: string, name: string, total: int}> */
    private function brandFacets(): Collection
    {
        $ids = $this->filtered('brands')->select('products.id');

        return Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->whereIn('products.id', $ids))
            ->withCount(['products' => fn (Builder $q) => $q->whereIn('products.id', $ids)])
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (Brand $brand): array => [
                'slug' => (string) $brand->slug,
                'name' => (string) $brand->name,
                'total' => (int) $brand->products_count,
            ]);
    }

    /**
     * The cheapest and dearest thing in the collection, so the price inputs can suggest a range
     * that actually exists rather than an empty one.
     *
     * @return array{min: ?float, max: ?float}
     */
    private function priceBounds(): array
    {
        $row = ProductVariant::query()
            ->selectRaw('min(retail_price) as low, max(retail_price) as high')
            ->where('is_active', true)
            ->whereNotNull('retail_price')
            ->whereIn('product_id', $this->filtered('price')->select('products.id'))
            ->first();

        return [
            'min' => $row?->low === null ? null : (float) $row->low,
            'max' => $row?->high === null ? null : (float) $row->high,
        ];
    }

    /**
     * Built here, not with @json in the view: the Blade directive stops at the first balanced
     * closing paren, which cuts a multi-line array literal in half and leaves the compiled view
     * a parse error — a 500 on every request, found only in production.
     */
    private function breadcrumbs(): string
    {
        $trail = [
            ['name' => 'Acasă', 'item' => route('storefront.home')],
            ['name' => 'Colecții', 'item' => route('storefront.collections')],
        ];

        // A derivative names its make on the way down, so the trail matches what the page shows
        // and search engines see the same hierarchy the visitor does.
        if ($this->collection->parent !== null) {
            $trail[] = ['name' => $this->collection->parent->name, 'item' => $this->collection->parent->url()];
        }

        $trail[] = ['name' => $this->collection->name, 'item' => $this->collection->url()];

        return (string) json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $crumb, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1] + $crumb,
                $trail,
                array_keys($trail),
            ),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The derivatives that actually have something in them, with how much.
     *
     * One grouped query over the pivot rather than a count per child: a make with three hundred
     * derivatives would otherwise be three hundred queries to draw one filter panel.
     *
     * @return Collection<int, array{slug: string, name: string, total: int}>
     */
    private function variantFacets(): Collection
    {
        $children = $this->children();

        if ($children->isEmpty()) {
            return new Collection;
        }

        $totals = DB::table('product_vehicle_collection')
            ->selectRaw('vehicle_collection_id, count(*) as total')
            ->whereIn('vehicle_collection_id', $children->pluck('id'))
            ->whereIn('product_id', $this->filtered('variants')->select('products.id'))
            ->groupBy('vehicle_collection_id')
            ->pluck('total', 'vehicle_collection_id');

        return $children
            ->map(fn (VehicleCollection $child): array => [
                'slug' => (string) $child->slug,
                'name' => (string) $child->name,
                'total' => (int) ($totals[$child->id] ?? 0),
            ])
            // A derivative the shop stocks nothing for is not a filter, it is a dead end. It
            // still appears in the hero's chooser, which is navigation rather than narrowing.
            ->filter(fn (array $facet): bool => $facet['total'] > 0)
            ->values();
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
