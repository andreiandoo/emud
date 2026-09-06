<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogSourceRecord;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class SourceRecordDetail extends Component
{
    public CatalogSourceRecord $record;

    public function mount(CatalogSourceRecord $record): void
    {
        $this->record = $record->load(['source', 'importRun', 'assertions']);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.source-record-detail');
    }
}
