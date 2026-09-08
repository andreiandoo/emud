<?php

namespace App\Livewire\Admin\Suppliers;

use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStrategicRole;
use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class SuppliersIndex extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $country = '';

    public function resetFilters(): void
    {
        $this->reset(['search', 'status', 'role', 'country']);
    }

    public function sync(int $supplierId, string $mode): void
    {
        abort_unless(in_array($mode, ['catalog', 'stock', 'prices'], true), 422);
        SyncSupplierFeed::dispatch($supplierId, $mode);
        session()->flash('status', 'Sincronizarea a fost adăugată în coadă.');
    }

    public function toggle(int $supplierId): void
    {
        $supplier = Supplier::query()->findOrFail($supplierId);
        $supplier->update(['is_active' => ! $supplier->is_active]);
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->with('syncSchedules')
            ->withCount('products')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(code) like ?', [$term]));
            })
            ->when($this->status !== '', fn ($query) => $query->where('onboarding_status', $this->status))
            ->when($this->role !== '', fn ($query) => $query->where('strategic_role', $this->role))
            ->when($this->country !== '', fn ($query) => $query->where('country_code', strtoupper($this->country)))
            ->orderByDesc('qualification_score')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.suppliers.suppliers-index', [
            // Configured feeds are the operational set; everything else is still a
            // commercial prospect and must not be shown with sync controls.
            'configured' => $suppliers->filter(
                fn (Supplier $supplier): bool => $supplier->is_active || $supplier->onboarding_status?->allowsIntegrationWork() === true,
            ),
            'prospects' => $suppliers->reject(
                fn (Supplier $supplier): bool => $supplier->is_active || $supplier->onboarding_status?->allowsIntegrationWork() === true,
            ),
            'pipeline' => Supplier::query()
                ->select('onboarding_status', DB::raw('count(*) as total'))
                ->groupBy('onboarding_status')
                ->pluck('total', 'onboarding_status'),
            'statuses' => SupplierOnboardingStatus::cases(),
            'roles' => SupplierStrategicRole::cases(),
            'countries' => Supplier::query()->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code'),
        ]);
    }
}
