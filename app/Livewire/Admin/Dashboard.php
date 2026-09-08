<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use App\Models\VehicleMake;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'metrics' => $this->metrics(),
            'orderStates' => $this->orderStates(),
            'catalogHealth' => $this->catalogHealth(),
            'recentOrders' => Order::query()->with('user:id,name')->latest('id')->limit(6)->get(),
            'recentRuns' => SupplierSyncRun::query()->with('supplier')->latest()->limit(6)->get(),
        ]);
    }

    /** @return array<string, array{value: string, label: string, tone?: string}> */
    private function metrics(): array
    {
        $currency = (string) config('emud.catalog.default_currency', 'RON');
        $since = Carbon::now()->startOfMonth();

        $monthOrders = Order::query()->where('created_at', '>=', $since)->get(['grand_total']);
        $revenue = Money::sum($monthOrders->map(fn (Order $order) => Money::of($order->grand_total, $currency)), $currency);
        $pending = Order::query()->where('status', 'pending')->count();

        return [
            'revenue' => ['value' => $revenue->format(), 'label' => 'Încasat luna aceasta'],
            'orders' => ['value' => number_format($monthOrders->count(), 0, ',', '.'), 'label' => 'Comenzi luna aceasta'],
            'pending' => ['value' => number_format($pending, 0, ',', '.'), 'label' => 'Comenzi de procesat', 'tone' => $pending > 0 ? 'warning' : 'neutral'],
            'products' => ['value' => number_format(Product::query()->count(), 0, ',', '.'), 'label' => 'Produse canonice'],
        ];
    }

    /** @return list<array{label: string, value: int, class: string}> */
    private function orderStates(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            ['label' => 'Finalizate', 'value' => (int) $counts->get('completed', 0), 'class' => 'bg-emerald-500'],
            ['label' => 'În lucru', 'value' => (int) ($counts->get('processing', 0) + $counts->get('confirmed', 0)), 'class' => 'bg-sky-500'],
            ['label' => 'În așteptare', 'value' => (int) $counts->get('pending', 0), 'class' => 'bg-amber-500'],
            ['label' => 'Anulate', 'value' => (int) $counts->get('cancelled', 0), 'class' => 'bg-red-500'],
        ];
    }

    /** @return array<string, int> */
    private function catalogHealth(): array
    {
        return [
            'Produse nemapate' => SupplierProduct::query()->where('mapping_status', 'unmapped')->count(),
            'Furnizori activi' => Supplier::query()->where('is_active', true)->count(),
            'Mărci auto' => VehicleMake::query()->count(),
        ];
    }
}
