<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class OrdersIndex extends Component
{
    use WithPagination;
    #[Url] public string $search = '';
    #[Url] public string $status = '';

    public function render()
    {
        return view('livewire.admin.orders-index', [
            'orders' => Order::query()->withCount('items')
                ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                    ->where('number', 'ilike', "%{$this->search}%")
                    ->orWhere('customer_email', 'ilike', "%{$this->search}%")))
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->latest()->paginate(25),
        ]);
    }
}
