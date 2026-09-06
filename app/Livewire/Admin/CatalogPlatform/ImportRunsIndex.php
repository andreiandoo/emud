<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogImportRun;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class ImportRunsIndex extends Component
{
    #[Url]
    public string $status = '';

    public function render()
    {
        return view('livewire.admin.catalog-platform.import-runs-index', [
            'runs' => CatalogImportRun::query()
                ->with('source')
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->latest()
                ->paginate(50),
        ]);
    }
}
