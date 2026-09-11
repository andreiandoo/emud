<?php

namespace App\Livewire\Storefront;

use App\Enums\StockStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use App\Storefront\VehicleSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One branch of the catalogue and everything needed to narrow it.
 *
 * Every facet is counted with the other filters applied and its own left out, the rule the
 * collection page follows too: an unticked box then says how many products ticking it would
 * leave, rather than zero.
 */
#[Layout('layouts::storefront', ['fullWidth' => true])]
class CategoryPage extends Component
{
    use WithPagination;

    public Category $category;

    /**
     * Subcategories to narrow to, by slug.
     *
     * @var list<string>
     */
    #[Url(as: 'sub', except: [])]
    public array $subcategories = [];

    /** @var list<string> */
    #[Url(as: 'marca', except: [])]
    public array $brands = [];

    #[Url(as: 'min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'max', except: '')]
    public string $priceMax = '';

    #[Url(as: 'stoc', except: false)]
    public bool $inStock = false;

    /**
     * Specification filters: attribute code => the values ticked for it. Each value carries its
     * kind in front — o12 is option 12, n50 the number 50, b1 "yes", t… a word — so one list can
     * hold whatever an attribute stores.
     *
     * @var array<string, list<string>>
     */
    #[Url(as: 'f', except: [])]
    public array $specs = [];

    #[Url(except: 'relevance')]
    public string $sort = 'relevance';

    /** Starts from the shop-wide setting (VehicleContext::filtersParts) and writes back to it. */
    public bool $onlyForMyVehicle = true;

    /** Set for the length of one render, so every count is taken against the same cars. */
    private ?VehicleSelection $vehicle = null;

    private ?FitmentMatcher $matcher = null;

    /** @var list<int>|null */
    private ?array $subtree = null;

    /** @var Collection<int, Category>|null */
    private ?Collection $ancestors = null;

    /** @var EloquentCollection<int, Attribute>|null */
    private ?EloquentCollection $specAttributes = null;

    public function mount(Category $category): void
    {
        $this->category = $category;
        $this->onlyForMyVehicle = app(VehicleContext::class)->filtersParts();
    }

    #[On('vehicle-changed')]
    public function vehicleChanged(): void
    {
        $this->onlyForMyVehicle = app(VehicleContext::class)->filtersParts();
        $this->resetPage();
    }

    /** Unticked here, unticked everywhere: the choice is the customer's, not this page's. */
    public function updatedOnlyForMyVehicle(bool $value): void
    {
        app(VehicleContext::class)->setFiltersParts($value);
        $this->dispatch('vehicle-changed');
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    /**
     * Ticks or unticks one specification value. A method rather than wire:model, because a list
     * of checkboxes only binds as a list once its key exists, and these keys exist only once
     * something has been ticked.
     */
    public function toggleSpec(string $code, string $value): void
    {
        $values = $this->chosenSpecs()[$code] ?? [];

        $values = in_array($value, $values, true)
            ? array_values(array_diff($values, [$value]))
            : [...$values, $value];

        $specs = $this->chosenSpecs();

        if ($values === []) {
            unset($specs[$code]);
        } else {
            $specs[$code] = $values;
        }

        $this->specs = $specs;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['subcategories', 'brands', 'priceMin', 'priceMax', 'inStock', 'specs']);
        $this->resetPage();
    }

    /** Removing one filter from the summary row, without opening the panel it came from. */
    public function removeFilter(string $type, string $value = '', string $code = ''): void
    {
        match ($type) {
            'subcategory' => $this->subcategories = array_values(array_diff($this->subcategories, [$value])),
            'brand' => $this->brands = array_values(array_diff($this->brands, [$value])),
            'price' => $this->reset(['priceMin', 'priceMax']),
            'stock' => $this->inStock = false,
            'spec' => $this->toggleSpec($code, $value),
            default => null,
        };

        $this->resetPage();
    }

    /** The fit-my-car filter is left out: it is on by default and has a switch of its own. */
    public function activeFilterCount(): int
    {
        return count($this->subcategories)
            + count($this->brands)
            + (($this->priceMin !== '' || $this->priceMax !== '') ? 1 : 0)
            + ($this->inStock ? 1 : 0)
            + array_sum(array_map('count', $this->chosenSpecs()));
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher)
    {
        $this->vehicle = $context->selection();
        $this->matcher = $matcher;
        $vehicle = $this->vehicle;

        return view('livewire.storefront.category-page', [
            'vehicle' => $vehicle,
            'products' => $this->sorted($this->filtered())->paginate(24),
            'verdicts' => fn (Product $product) => $vehicle === null ? null : $matcher->verdictFor($product, $vehicle),
            'ancestors' => $this->ancestors(),
            'subcategoryFacets' => $this->subcategoryFacets(),
            'brandFacets' => $this->brandFacets(),
            'specFacets' => $this->specFacets(),
            'priceBounds' => $this->priceBounds(),
            'chosenSpecs' => $this->chosenSpecs(),
            'activeFilters' => $this->activeFilterCount(),
        ]);
    }

    /** Everything in this branch, with every filter applied except the ones named. */
    private function filtered(string ...$except): Builder
    {
        $skip = array_flip($except);

        $query = Product::query()
            ->active()
            ->with(['brand', 'media', 'fitments', 'variants'])
            ->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $this->subtree()));

        if (! isset($skip['subcategories']) && ($under = $this->idsUnder($this->subcategories)) !== []) {
            $query->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $under));
        }

        if (! isset($skip['brands']) && $this->brands !== []) {
            $query->whereHas('brand', fn (Builder $q) => $q->whereIn('slug', $this->brands));
        }

        if (! isset($skip['price']) && ($this->priceMin !== '' || $this->priceMax !== '')) {
            $query->whereHas('variants', function (Builder $q): void {
                $q->where('is_active', true)->whereNotNull('retail_price');

                if (is_numeric($this->priceMin)) {
                    $q->where('retail_price', '>=', (float) $this->priceMin);
                }

                if (is_numeric($this->priceMax)) {
                    $q->where('retail_price', '<=', (float) $this->priceMax);
                }
            });
        }

        if (! isset($skip['stock']) && $this->inStock) {
            // Stock lives on supplier offers, two relations away. "Available" means a supplier
            // has it now — backorder is not the same promise, so it is left out.
            $query->whereHas('supplierProducts', fn (Builder $q) => $q->whereHas('offer', fn (Builder $o) => $o
                ->where('is_active', true)
                ->whereIn('stock_status', [StockStatus::InStock->value, StockStatus::LowStock->value])));
        }

        foreach ($this->chosenSpecs() as $code => $values) {
            if (! isset($skip['spec:'.$code])) {
                $this->whereSpec($query, $code, $values);
            }
        }

        // Part of every count, not only the listing: on by default, so a count taken without it
        // would promise products the grid then does not show.
        if ($this->vehicle !== null && $this->matcher !== null && $this->onlyForMyVehicle) {
            $this->matcher->scopeForVehicle($query, $this->vehicle);
        }

        return $query;
    }

    private function sorted(Builder $query): Builder
    {
        // The cheapest active variant through a correlated subquery rather than a join: a
        // product with three variants would otherwise be listed three times.
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
            // Relevance without a search term is arbitrary, so featured products lead and the
            // rest keep a stable order rather than whatever the planner returns.
            default => $query->orderByDesc('is_featured')->orderBy('id'),
        };
    }

    /**
     * A category page shows the whole subtree: a customer browsing "Suspensie" expects the
     * products filed under its subcategories too.
     *
     * @return list<int>
     */
    private function subtree(): array
    {
        return $this->subtree ??= Category::query()
            ->where('id', $this->category->id)
            ->orWhere('full_path', 'like', $this->category->full_path.'/%')
            ->pluck('id')
            ->all();
    }

    /**
     * Everything filed under the chosen subcategories. Slugs that are not a child of this
     * category — an old link, a hand-edited URL — are ignored rather than emptying the page.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function idsUnder(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $paths = Category::query()
            ->where('parent_id', $this->category->id)
            ->whereIn('slug', $slugs)
            ->pluck('full_path');

        if ($paths->isEmpty()) {
            return [];
        }

        return Category::query()
            ->where(function (Builder $q) use ($paths): void {
                foreach ($paths as $path) {
                    $q->orWhere('full_path', $path)->orWhere('full_path', 'like', $path.'/%');
                }
            })
            ->pluck('id')
            ->all();
    }

    /** @return Collection<int, Category> from the top of the tree down to the parent */
    private function ancestors(): Collection
    {
        if ($this->ancestors !== null) {
            return $this->ancestors;
        }

        $trail = collect();
        $parentId = $this->category->parent_id;

        // Bounded, so a cycle an import once wrote cannot hold the page forever.
        while ($parentId !== null && $trail->count() < 8) {
            $parent = Category::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            $trail->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $this->ancestors = $trail;
    }

    /**
     * The categories directly below this one, each with how many products under it the other
     * filters leave.
     *
     * @return Collection<int, array{slug: string, name: string, total: int}>
     */
    private function subcategoryFacets(): Collection
    {
        $children = $this->category->children()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'full_path']);

        if ($children->isEmpty()) {
            return collect();
        }

        // One query for the whole subtree rather than one per child; which child a row belongs
        // to is read off its path.
        $rows = DB::table('category_product')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->whereIn('category_product.category_id', $this->subtree())
            ->whereIn('category_product.product_id', $this->filtered('subcategories')->select('products.id'))
            ->get(['category_product.product_id', 'categories.full_path']);

        return $children
            ->map(function (Category $child) use ($rows): array {
                $prefix = $child->full_path.'/';

                return [
                    'slug' => (string) $child->slug,
                    'name' => (string) $child->name,
                    'total' => $rows
                        ->filter(fn (object $row): bool => $row->full_path === $child->full_path || str_starts_with($row->full_path, $prefix))
                        ->pluck('product_id')
                        ->unique()
                        ->count(),
                ];
            })
            ->filter(fn (array $facet): bool => $facet['total'] > 0 || in_array($facet['slug'], $this->subcategories, true))
            ->values();
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
            ->limit(40)
            ->get()
            ->map(fn (Brand $brand): array => [
                'slug' => (string) $brand->slug,
                'name' => (string) $brand->name,
                'total' => (int) $brand->products_count,
            ]);
    }

    /**
     * What this branch can be narrowed by: attributes an operator marked filterable on this
     * category, on one above it, or on one below it, since the page lists the whole subtree.
     *
     * @return EloquentCollection<int, Attribute>
     */
    private function specAttributes(): EloquentCollection
    {
        return $this->specAttributes ??= Attribute::query()
            ->where('is_active', true)
            ->whereHas('categories', fn (Builder $q) => $q
                ->whereIn('categories.id', [...$this->subtree(), ...$this->ancestors()->pluck('id')->all()])
                ->where('attribute_category.is_filterable', true))
            ->with('options')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, array{code: string, name: string, values: list<array{value: string, label: string, total: int}>}>
     */
    private function specFacets(): Collection
    {
        return $this->specAttributes()
            ->map(function (Attribute $attribute): ?array {
                $values = ProductAttributeValue::query()
                    ->where('attribute_id', $attribute->id)
                    ->whereNull('variant_id')
                    ->whereIn('product_id', $this->filtered('spec:'.$attribute->code)->select('products.id'))
                    ->select(['option_id', 'value_number', 'value_text', 'value_boolean'])
                    ->selectRaw('count(distinct product_id) as total')
                    ->groupBy(['option_id', 'value_number', 'value_text', 'value_boolean'])
                    ->orderByDesc('total')
                    ->limit(30)
                    ->get()
                    ->map(fn (ProductAttributeValue $row): ?array => $this->facetValue($attribute, $row))
                    ->filter()
                    ->sortBy('label', SORT_NATURAL)
                    ->values()
                    ->all();

                // One value narrows nothing — unless it is ticked, and has to stay untickable.
                if (count($values) < 2 && ! isset($this->chosenSpecs()[$attribute->code])) {
                    return null;
                }

                return ['code' => (string) $attribute->code, 'name' => (string) $attribute->name, 'values' => $values];
            })
            ->filter()
            ->values();
    }

    /** @return array{value: string, label: string, total: int}|null */
    private function facetValue(Attribute $attribute, ProductAttributeValue $row): ?array
    {
        $total = (int) $row->getAttribute('total');

        if ($row->option_id !== null) {
            $option = $attribute->options->firstWhere('id', $row->option_id);

            return $option === null ? null : ['value' => 'o'.$row->option_id, 'label' => (string) $option->label, 'total' => $total];
        }

        if ($row->value_boolean !== null) {
            return ['value' => $row->value_boolean ? 'b1' : 'b0', 'label' => $row->value_boolean ? 'Da' : 'Nu', 'total' => $total];
        }

        if ($row->value_number !== null) {
            // Six stored decimals, trimmed: nobody wants to tick "50.000000 mm".
            $number = rtrim(rtrim((string) $row->value_number, '0'), '.');
            $number = $number === '' ? '0' : $number;

            return ['value' => 'n'.$number, 'label' => trim($number.' '.(string) $attribute->unit), 'total' => $total];
        }

        $text = trim((string) $row->value_text);

        // A paragraph is a description, not something to tick.
        return $text === '' || mb_strlen($text) > 60 ? null : ['value' => 't'.$text, 'label' => $text, 'total' => $total];
    }

    /** @param  list<string>  $values */
    private function whereSpec(Builder $query, string $code, array $values): void
    {
        $attribute = $this->specAttributes()->firstWhere('code', $code);

        if ($attribute === null) {
            return;
        }

        $query->whereHas('attributeValues', fn (Builder $q) => $q
            ->where('attribute_id', $attribute->id)
            ->whereNull('variant_id')
            ->where(function (Builder $any) use ($values): void {
                foreach ($values as $value) {
                    $kind = substr($value, 0, 1);
                    $content = substr($value, 1);

                    if ($kind === 'o' && ctype_digit($content)) {
                        $any->orWhere('option_id', (int) $content);
                    } elseif ($kind === 'n' && is_numeric($content)) {
                        $any->orWhere('value_number', $content);
                    } elseif ($kind === 'b') {
                        $any->orWhere('value_boolean', $content === '1');
                    } elseif ($kind === 't' && $content !== '') {
                        $any->orWhere('value_text', $content);
                    }
                }
            }));
    }

    /**
     * The specification filters as they are safe to use: code => list of values. The property
     * arrives from the URL, where anything can be typed into it.
     *
     * @return array<string, list<string>>
     */
    private function chosenSpecs(): array
    {
        $chosen = [];

        foreach ($this->specs as $code => $values) {
            if (! is_string($code) || ! is_array($values)) {
                continue;
            }

            $values = array_values(array_filter($values, 'is_string'));

            if ($values !== []) {
                $chosen[$code] = $values;
            }
        }

        return $chosen;
    }

    /**
     * The cheapest and dearest thing the other filters leave, so the price inputs can suggest a
     * range that actually exists.
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
}
