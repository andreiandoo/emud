<?php

namespace App\Livewire\Storefront;

use App\Models\VehicleCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every car the shop has a page for, grouped under its make.
 *
 * A flat alphabetical list of several hundred entries is a wall; grouped by make it is a table
 * of contents, and the make row is itself a collection worth clicking.
 */
#[Layout('layouts::storefront')]
class CollectionsIndex extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public function render()
    {
        return view('livewire.storefront.collections-index', [
            'groups' => $this->groups(),
            'featured' => app(\App\Storefront\CollectionShowcase::class)->featured(12),
        ]);
    }

    /**
     * @return Collection<int, array{make: ?VehicleCollection, name: string, models: Collection<int, VehicleCollection>}>
     */
    private function groups(): Collection
    {
        $collections = VehicleCollection::query()
            ->active()
            ->with('make:id,name')
            ->when($this->search !== '', function (Builder $query): void {
                $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($this->search).'%']);
            })
            ->orderBy('name')
            ->get();

        return $collections
            // Grouped on the make id and not on the name: two makes can share a name in the
            // reference data, and merging them would file a Jimny under the wrong company.
            ->groupBy(fn (VehicleCollection $collection): string => (string) ($collection->make_id ?? 'x'))
            ->map(function (Collection $rows): array {
                $makeLevel = $rows->firstWhere('model_id', null);

                return [
                    'make' => $makeLevel,
                    'name' => $makeLevel?->name ?? $rows->first()->make?->name ?? $rows->first()->name,
                    'models' => $rows->filter(fn (VehicleCollection $row): bool => $row->model_id !== null)->values(),
                ];
            })
            ->sortBy('name')
            ->values();
    }
}
