<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class SourceRecordsIndex extends Component
{
    use WithPagination;

    public CatalogSource $source;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $type = '';

    public function mount(CatalogSource $source): void
    {
        $this->source = $source;
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $records = CatalogSourceRecord::query()
            ->where('catalog_source_id', $this->source->id)
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('external_id', 'ilike', "%{$this->search}%")->orWhere('mapping_notes', 'ilike', "%{$this->search}%")))
            ->when($this->status, fn ($q) => $q->where('mapping_status', $this->status))
            ->when($this->type, fn ($q) => $q->where('record_type', $this->type))
            ->latest('id')
            ->paginate(50);

        $types = CatalogSourceRecord::query()->where('catalog_source_id', $this->source->id)->select('record_type')->distinct()->orderBy('record_type')->pluck('record_type');

        return view('livewire.admin.catalog-platform.source-records-index', compact('records', 'types'));
    }
}
