<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceShop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every workshop in one town.
 *
 * This is the page a search for "service auto Cluj" should land on, which is why the city is in
 * the URL rather than in a query string.
 *
 * It also catches the old one-segment workshop links. Before the city was part of the address a
 * workshop lived at /service-auto/{slug}, which is the same shape as this route; rather than
 * leaving those bookmarks to 404, a segment that matches no city is looked up as a workshop
 * slug and sent to its current address with a permanent redirect.
 */
#[Layout('layouts::storefront')]
class ServiceCityPage extends Component
{
    use WithPagination;

    public string $city = '';

    public string $cityName = '';

    #[Url(except: '')]
    public string $speciality = '';

    #[Url(except: false)]
    public bool $fitsOurParts = false;

    #[Url(except: false)]
    public bool $openNow = false;

    public function mount(string $city): void
    {
        $this->city = $city;

        $name = ServiceShop::query()->published()->where('city_slug', $city)->value('city');

        if ($name === null) {
            $shop = ServiceShop::query()->published()->where('slug', $city)->first();

            abort_if($shop === null, 404);

            $this->permanentRedirect($shop->url());
        }

        $this->cityName = (string) $name;
    }

    /**
     * A permanent redirect from inside mount().
     *
     * Livewire's own redirect() is always a 302, and these two are permanent moves — an address
     * that changed shape and a workshop reached at the wrong city segment. Throwing the response
     * is Laravel's own mechanism for returning early from somewhere that cannot return a
     * response, which is exactly the position a Livewire mount is in.
     */
    private function permanentRedirect(string $url): never
    {
        throw new HttpResponseException(redirect()->to($url, 301));
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.storefront.service-city-page', [
            'shops' => $this->shops(),
            'specialities' => $this->specialities(),
        ]);
    }

    private function shops(): LengthAwarePaginator
    {
        return $this->base()
            ->with(['hours', 'services'])
            ->promotedFirst()
            ->orderBy('name')
            ->paginate(20);
    }

    private function base(): Builder
    {
        return ServiceShop::query()
            ->published()
            ->where('city_slug', $this->city)
            ->when($this->speciality !== '', fn (Builder $q) => $q->whereJsonContains('specialities', $this->speciality))
            ->when($this->fitsOurParts, fn (Builder $q) => $q->where('fits_parts_bought_here', true))
            ->when($this->openNow, fn (Builder $q) => $q->openNow());
    }

    /** @return Collection<int, string> */
    private function specialities(): Collection
    {
        return ServiceShop::query()
            ->published()
            ->where('city_slug', $this->city)
            ->pluck('specialities')
            ->flatMap(fn (mixed $list): array => is_array($list) ? $list : [])
            ->unique()
            ->sort()
            ->values();
    }
}
