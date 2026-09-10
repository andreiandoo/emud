<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class Orders extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.customer.orders', [
            // Scoped to the signed-in customer rather than filtered in the view, so a template
            // change can never widen what is listed.
            'orders' => Order::query()
                ->where('user_id', auth()->id())
                ->with('items')
                ->latest('id')
                ->paginate(10),
        ]);
    }
}
