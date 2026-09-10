<?php

namespace App\Livewire\Storefront;

use App\Models\VehicleCollection;
use App\Storefront\CatalogMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every car the shop has a page for, as a wall of pictures.
 *
 * Deliberately not a directory. An alphabetical list of several hundred model names is a thing
 * nobody reads: a visitor arrives knowing exactly what they drive, so the page gives them one
 * box to type it into and otherwise shows them cars. What is not on screen is reachable through
 * the search, not through more rows of text.
 */
#[Layout('layouts::storefront', ['fullWidth' => true])]
class CollectionsIndex extends Component
{
    /** How many tiles a screenful is. Chosen to fill the widest grid exactly. */
    private const PER_PAGE = 30;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public int $perPage = self::PER_PAGE;

    /** Typing a new term starts the wall again from the top. */
    public function updatedSearch(): void
    {
        $this->perPage = self::PER_PAGE;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->perPage = self::PER_PAGE;
    }

    public function loadMore(): void
    {
        $this->perPage += self::PER_PAGE;
    }

    public function render()
    {
        $total = $this->query()->count();

        return view('livewire.storefront.collections-index', [
            'tiles' => $this->tiles(),
            'total' => $total,
            'hasMore' => $total > $this->perPage,
            // A zero is worse than a silence: "0 repere în catalogul tehnic" advertises an
            // empty shop, and on a fresh install that is exactly what it would say.
            'metrics' => array_filter(
                app(CatalogMetrics::class)->snapshot(),
                static fn (array $metric): bool => $metric['value'] > 0,
            ),
        ]);
    }

    /** @return Collection<int, VehicleCollection> */
    private function tiles(): Collection
    {
        return $this->query()
            // A collection with a photograph earns its place on the wall ahead of one without,
            // whatever else is true of it: this page is the pictures.
            ->orderByRaw('case when square_image_path is null then 1 else 0 end')
            ->orderByDesc('is_featured')
            ->orderBy('position')
            ->orderBy('name')
            ->limit($this->perPage)
            ->get(['id', 'name', 'slug', 'square_image_path', 'subtitle', 'year_from', 'year_to']);
    }

    private function query(): Builder
    {
        return VehicleCollection::query()
            ->active()
            ->when($this->search !== '', function (Builder $query): void {
                $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower(trim($this->search)).'%']);
            });
    }
}
