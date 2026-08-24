<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\SupplierSyncRun;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class SyncRunsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function render()
    {
        return view('livewire.admin.suppliers.sync-runs-index', [
            'runs' => SupplierSyncRun::query()->with('supplier')
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->latest()->paginate(30),
        ]);
    }
}
