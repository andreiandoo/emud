<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use App\Models\VehicleMake;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'metrics' => [
                'Produse canonice' => Product::query()->count(),
                'Produse nemapate' => SupplierProduct::query()->where('mapping_status', 'unmapped')->count(),
                'Furnizori activi' => Supplier::query()->where('is_active', true)->count(),
                'Mărci auto' => VehicleMake::query()->count(),
                'Comenzi noi' => Order::query()->where('status', 'pending')->count(),
            ],
            'recentRuns' => SupplierSyncRun::query()->with('supplier')->latest()->limit(8)->get(),
        ]);
    }
}
