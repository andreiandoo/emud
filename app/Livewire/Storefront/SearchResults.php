<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class SearchResults extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $query = '';

    /** Starts from the shop-wide setting (VehicleContext::filtersParts) and writes back to it. */
    public bool $onlyForMyVehicle = true;

    public function mount(VehicleContext $context): void
    {
        $this->onlyForMyVehicle = $context->filtersParts();
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function updatedOnlyForMyVehicle(bool $value): void
    {
        app(VehicleContext::class)->setFiltersParts($value);
        $this->dispatch('vehicle-changed');
    }

    #[On('vehicle-changed')]
    public function vehicleChanged(): void
    {
        $this->onlyForMyVehicle = app(VehicleContext::class)->filtersParts();
        $this->resetPage();
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher)
    {
        $vehicle = $context->selection();

        return view('livewire.storefront.search-results', [
            'vehicle' => $vehicle,
            'products' => $this->results($vehicle, $matcher),
            'verdicts' => fn (Product $product) => $matcher->verdictFor($product, $vehicle),
        ]);
    }

    private function results(mixed $vehicle, FitmentMatcher $matcher): LengthAwarePaginator
    {
        $term = trim($this->query);

        if ($term === '') {
            return Product::query()->whereRaw('1 = 0')->paginate(24);
        }

        $products = Product::query()
            ->active()
            ->with(['brand', 'media', 'fitments', 'variants'])
            ->where(function (Builder $query) use ($term): void {
                // Part numbers are what customers paste in most often, so they are matched
                // exactly rather than as a substring: an exact SKU should not be buried under
                // every product whose description happens to contain the digits.
                $query->where('sku', $term)
                    ->orWhere('manufacturer_part_number', $term)
                    ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', $term)
                        ->orWhere('barcode', $term)
                        ->orWhere('manufacturer_part_number', $term))
                    // The EAN printed on the box usually arrives with a supplier's feed and is
                    // kept on the supplier's record, not copied onto the product.
                    ->orWhereHas('supplierProducts', fn (Builder $s) => $s->where('ean', $term))
                    // lower() rather than ILIKE: the suite runs on SQLite as well as PostgreSQL,
                    // and ILIKE exists only on the latter.
                    ->orWhereRaw('lower(name) like ?', ['%'.mb_strtolower($term).'%']);
            });

        if ($vehicle !== null && $this->onlyForMyVehicle) {
            $matcher->scopeForVehicle($products, $vehicle);
        }

        return $products->orderByDesc('is_featured')->orderBy('name')->paginate(24);
    }
}
