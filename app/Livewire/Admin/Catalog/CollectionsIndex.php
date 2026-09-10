<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\VehicleCollection;
use App\Storefront\CollectionShowcase;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class CollectionsIndex extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const TABS = [
        '' => 'Toate',
        'roots' => 'Principale',
        'children' => 'Secundare',
        'featured' => 'În carusel',
        'without-image' => 'Fără imagine',
        'hidden' => 'Ascunse',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $tab = '';

    public string $flash = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function toggleFeatured(int $collectionId): void
    {
        $collection = VehicleCollection::query()->findOrFail($collectionId);
        $collection->update(['is_featured' => ! $collection->is_featured]);

        // The carousel is cached for fifteen minutes on every page of the shop, so a flag
        // flipped here would otherwise appear to do nothing.
        CollectionShowcase::forget();

        $this->flash = $collection->is_featured
            ? $collection->name.' apare acum în caruselul de pe prima pagină.'
            : $collection->name.' a fost scoasă din carusel.';
    }

    public function toggleActive(int $collectionId): void
    {
        $collection = VehicleCollection::query()->findOrFail($collectionId);
        $collection->update(['is_active' => ! $collection->is_active]);

        CollectionShowcase::forget();

        $this->flash = $collection->is_active
            ? $collection->name.' este publicată.'
            : $collection->name.' este ascunsă din magazin.';
    }

    public function render()
    {
        return view('livewire.admin.catalog.collections-index', [
            'collections' => $this->query()
                ->with(['make:id,name', 'parent:id,name'])
                ->withCount(['products', 'reviews', 'children'])
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(25),
            'tabs' => self::TABS,
            'counts' => $this->counts(),
        ]);
    }

    private function query(): Builder
    {
        return VehicleCollection::query()
            ->when($this->search !== '', fn (Builder $q) => $q
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($this->search).'%']))
            ->when($this->tab === 'roots', fn (Builder $q) => $q->whereNull('parent_id'))
            ->when($this->tab === 'children', fn (Builder $q) => $q->whereNotNull('parent_id'))
            ->when($this->tab === 'featured', fn (Builder $q) => $q->where('is_featured', true))
            ->when($this->tab === 'without-image', fn (Builder $q) => $q->whereNull('square_image_path'))
            ->when($this->tab === 'hidden', fn (Builder $q) => $q->where('is_active', false));
    }

    /**
     * The seeder can create several thousand rows, so "how many still have no picture" is the
     * number this screen exists to answer.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            '' => VehicleCollection::query()->count(),
            'roots' => VehicleCollection::query()->whereNull('parent_id')->count(),
            'children' => VehicleCollection::query()->whereNotNull('parent_id')->count(),
            'featured' => VehicleCollection::query()->where('is_featured', true)->count(),
            'without-image' => VehicleCollection::query()->whereNull('square_image_path')->count(),
            'hidden' => VehicleCollection::query()->where('is_active', false)->count(),
        ];
    }
}
