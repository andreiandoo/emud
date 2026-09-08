<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceShop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront')]
class ServiceDirectory extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $county = '';

    #[Url(except: '')]
    public string $city = '';

    #[Url(except: '')]
    public string $speciality = '';

    #[Url(except: false)]
    public bool $fitsOurParts = false;

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }

        if ($property === 'county') {
            $this->city = '';
        }
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
        ]);
    }

    private function shops(): LengthAwarePaginator
    {
        return ServiceShop::query()
            ->published()
            ->when($this->county !== '', fn (Builder $q) => $q->where('county', $this->county))
            ->when($this->city !== '', fn (Builder $q) => $q->where('city', $this->city))
            ->when($this->speciality !== '', fn (Builder $q) => $q->whereJsonContains('specialities', $this->speciality))
            ->when($this->fitsOurParts, fn (Builder $q) => $q->where('fits_parts_bought_here', true))
            // Expired promotions must not keep their position, so the ordering only counts a
            // tier whose end date has not passed.
            ->orderByRaw($this->promotionOrdering())
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Written as SQL rather than sorted in PHP so the ordering survives pagination: sorting a
     * page in memory would rank twenty rows against each other, not the whole directory.
     */
    private function promotionOrdering(): string
    {
        return "CASE WHEN promoted_until IS NOT NULL AND promoted_until < CURRENT_DATE THEN 0
                     WHEN promotion_tier = 'premium' THEN 3
                     WHEN promotion_tier = 'featured' THEN 2
                     WHEN promotion_tier = 'listed' THEN 1
                     ELSE 0 END DESC";
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
