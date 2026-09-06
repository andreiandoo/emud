<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogConflict;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class ConflictsIndex extends Component
{
    #[Url]
    public string $status = 'open';

    #[Url]
    public string $type = '';

    public function resolve(int $conflictId, string $resolution = 'reviewed'): void
    {
        CatalogConflict::query()->findOrFail($conflictId)->update([
            'status' => 'resolved',
            'resolution' => ['action' => $resolution],
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.conflicts-index', [
            'conflicts' => CatalogConflict::query()
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->when($this->type, fn ($query) => $query->where('entity_type', $this->type))
                ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
                ->latest()
                ->paginate(50),
        ]);
    }
}
