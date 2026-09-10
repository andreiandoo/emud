<?php

namespace App\Livewire\Admin\Workshops;

use App\Models\WorkshopSourceRecord;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One source record exactly as it was received, with what it was turned into. Page HTML is shown
 * as escaped text, never rendered.
 */
#[Layout('layouts::admin')]
class WorkshopSourceRecordDetail extends Component
{
    public WorkshopSourceRecord $record;

    public function mount(WorkshopSourceRecord $record): void
    {
        $this->record = $record->load(['dataSource', 'importRun', 'link.workshop:id,name', 'matches']);
    }

    public function render()
    {
        return view('livewire.admin.workshops.source-record', [
            'rawExcerpt' => $this->record->raw_content === null ? null : mb_substr($this->record->raw_content, 0, 20_000),
        ]);
    }
}
