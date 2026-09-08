<?php

namespace App\Livewire\Admin\Suppliers;

use App\Models\SupplierSyncError;
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

    #[Url(except: '')]
    public string $mode = '';

    /** Run whose error rows are expanded, if any. */
    public ?int $inspecting = null;

    public function inspect(int $runId): void
    {
        $this->inspecting = $this->inspecting === $runId ? null : $runId;
    }

    public function render()
    {
        return view('livewire.admin.suppliers.sync-runs-index', [
            'runs' => SupplierSyncRun::query()->with('supplier')
                ->withCount('errors')
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->when($this->mode, fn ($query) => $query->where('mode', $this->mode))
                ->latest()->paginate(30),
            // Capped: a broken feed can produce thousands of rows and the point of the
            // panel is to show the pattern, not to page through every instance.
            'errors' => $this->inspecting
                ? SupplierSyncError::query()->where('supplier_sync_run_id', $this->inspecting)->orderBy('id')->limit(50)->get()
                : collect(),
        ]);
    }
}
