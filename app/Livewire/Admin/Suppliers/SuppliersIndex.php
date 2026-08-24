<?php

namespace App\Livewire\Admin\Suppliers;

use App\Jobs\SyncSupplierFeed;
use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class SuppliersIndex extends Component
{
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
        return view('livewire.admin.suppliers.suppliers-index', [
            'suppliers' => Supplier::query()->withCount('products')->latest()->get(),
        ]);
    }
}
