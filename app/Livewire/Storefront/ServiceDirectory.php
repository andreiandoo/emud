<?php

namespace App\Livewire\Storefront;

use App\Models\Service;
use App\Models\ServiceShop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class ServiceDirectory extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $county = '';

    #[Url(except: '')]
    public string $city = '';

    #[Url(except: '')]
    public string $speciality = '';

    #[Url(except: '')]
    public string $service = '';

    #[Url(except: false)]
    public bool $fitsOurParts = false;

    #[Url(except: false)]
    public bool $openNow = false;

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }

        if ($property === 'county') {
            $this->city = '';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->county = '';
        $this->city = '';
        $this->speciality = '';
        $this->service = '';
        $this->fitsOurParts = false;
        $this->openNow = false;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.storefront.service-directory', [
            'shops' => $this->shops(),
            'counties' => ServiceShop::query()->published()->distinct()->orderBy('county')->pluck('county'),
            'cities' => $this->county === ''
                ? collect()
                : ServiceShop::query()->published()->where('county', $this->county)->distinct()->orderBy('city')->pluck('city'),
            'specialities' => $this->availableSpecialities(),
            'serviceOptions' => Service::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'topCities' => $this->topCities(),
        ]);
    }

    private function shops(): LengthAwarePaginator
    {
        return ServiceShop::query()
            ->published()
            ->with(['hours', 'services'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $query->where(fn (Builder $inner) => $inner
                    ->whereRaw('lower(name) like ?', [$term])
                    ->orWhereRaw('lower(city) like ?', [$term])
                    ->orWhereRaw('lower(address) like ?', [$term]));
            })
            ->when($this->county !== '', fn (Builder $q) => $q->where('county', $this->county))
            ->when($this->city !== '', fn (Builder $q) => $q->where('city', $this->city))
            ->when($this->speciality !== '', fn (Builder $q) => $q->whereJsonContains('specialities', $this->speciality))
            ->when($this->service !== '', fn (Builder $q) => $q->whereHas('services', fn (Builder $inner) => $inner->where('services.slug', $this->service)))
            ->when($this->fitsOurParts, fn (Builder $q) => $q->where('fits_parts_bought_here', true))
            ->when($this->openNow, fn (Builder $q) => $q->openNow())
            ->promotedFirst()
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * The towns with the most listings, as links rather than another dropdown. A directory is
     * mostly used by people who already know which town they are in.
     *
     * @return Collection<int, object>
     */
    private function topCities(): Collection
    {
        return ServiceShop::query()
            ->published()
            ->selectRaw('city, city_slug, count(*) as total')
            ->whereNotNull('city_slug')
            ->groupBy('city', 'city_slug')
            ->orderByDesc('total')
            ->orderBy('city')
            ->limit(12)
            ->get();
    }

    /** @return Collection<int, string> */
    private function availableSpecialities(): Collection
    {
        return ServiceShop::query()
            ->published()
            ->pluck('specialities')
            ->flatMap(fn (mixed $list): array => is_array($list) ? $list : [])
            ->unique()
            ->sort()
            ->values();
    }
}
