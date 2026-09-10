<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceShop;
use App\Storefront\VehicleContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One job, explained, priced, and offered — by workshops and, where it applies, by the shop.
 *
 * This is the page that makes the service taxonomy earn its keep: someone searching for what a
 * timing belt change costs arrives here, and leaves with either a workshop or the parts.
 */
#[Layout('layouts::storefront', ['fullWidth' => true])]
class ServiceTypePage extends Component
{
    use WithPagination;

    public Service $service;

    #[Url(except: '')]
    public string $city = '';

    public function mount(string $slug): void
    {
        $this->service = Service::query()
            ->active()
            ->with(['serviceCategory', 'partsCategory'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.storefront.service-type-page', [
            'shops' => $this->shops(),
            'cities' => ServiceShop::query()
                ->published()
                ->whereHas('services', fn (Builder $q) => $q->whereKey($this->service->id))
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'parts' => $this->parts(),
        ]);
    }

    private function shops(): LengthAwarePaginator
    {
        return ServiceShop::query()
            ->published()
            ->with(['hours', 'services' => fn ($query) => $query->whereKey($this->service->id)])
            ->whereHas('services', fn (Builder $q) => $q->whereKey($this->service->id))
            ->when($this->city !== '', fn (Builder $q) => $q->where('city', $this->city))
            ->promotedFirst()
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Parts for this job, narrowed to the visitor's car when one is selected. Without a vehicle
     * the list is still worth showing — someone reading about brake pads wants to see brake
     * pads — but it is labelled as unfiltered rather than implied to fit.
     *
     * @return EloquentCollection<int, Product>
     */
    private function parts(): EloquentCollection
    {
        if ($this->service->partsCategory === null) {
            return new EloquentCollection;
        }

        $vehicle = app(VehicleContext::class)->current();

        return Product::query()
            ->active()
            ->with(['brand', 'media', 'variants'])
            ->whereHas('categories', fn (Builder $q) => $q->whereKey($this->service->partsCategory->id))
            ->when($vehicle !== null, fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('is_universal', true)
                ->orWhereHas('fitments', fn (Builder $fitment) => $fitment->where('model_id', $vehicle->modelId))))
            ->limit(8)
            ->get();
    }
}
