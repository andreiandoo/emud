<?php

namespace App\Livewire\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront')]
class CategoryPage extends Component
{
    use WithPagination;

    public Category $category;

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: 'relevance')]
    public string $sort = 'relevance';

    /** Kept in the URL so a filtered listing stays shareable and reproducible. */
    #[Url(except: true)]
    public bool $onlyForMyVehicle = true;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    #[On('vehicle-changed')]
    public function vehicleChanged(): void
    {
        $this->resetPage();
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

        return view('livewire.storefront.category-page', [
            'vehicle' => $vehicle,
            'products' => $this->products($vehicle, $matcher),
            'brands' => $this->availableBrands(),
            'verdicts' => fn (Product $product) => $matcher->verdictFor($product, $vehicle),
            'children' => $this->category->children()->where('is_active', true)->orderBy('position')->get(),
        ]);
    }

    private function products(mixed $vehicle, FitmentMatcher $matcher): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['brand', 'media', 'fitments', 'variants'])
            ->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $this->categoryIds()));

        if ($this->brand !== '') {
            $query->whereHas('brand', fn (Builder $q) => $q->where('slug', $this->brand));
        }

        if ($vehicle !== null && $this->onlyForMyVehicle) {
            $matcher->scopeForVehicle($query, $vehicle);
        }

        return $this->applySort($query)->paginate(24);
    }

    private function applySort(Builder $query): Builder
    {
        return match ($this->sort) {
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('published_at'),
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
    private function categoryIds(): array
    {
        return Category::query()
            ->where('id', $this->category->id)
            ->orWhere('full_path', 'like', $this->category->full_path.'/%')
            ->pluck('id')
            ->all();
    }

    /** @return Collection<int, Brand> */
    private function availableBrands(): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->active()
                ->whereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $this->categoryIds())))
            ->orderBy('name')
            ->get();
    }
}
