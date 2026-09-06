<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Jobs\SyncCatalogSource;
use App\Models\CatalogSource;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class SourcesIndex extends Component
{
    #[Url]
    public string $search = '';

    public function sync(int $sourceId, string $mode = 'catalog'): void
    {
        SyncCatalogSource::dispatch($sourceId, $mode);
        session()->flash('status', 'Catalog source synchronization queued.');
    }

    public function toggle(int $sourceId): void
    {
        $source = CatalogSource::query()->findOrFail($sourceId);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.sources-index', [
            'sources' => CatalogSource::query()
                ->withCount(['records', 'importRuns'])
                ->when($this->search, fn ($query) => $query->where(function ($query): void {
                    $query->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('code', 'ilike', "%{$this->search}%")
                        ->orWhere('source_type', 'ilike', "%{$this->search}%");
                }))
                ->orderBy('name')
                ->paginate(30),
        ]);
    }
}
