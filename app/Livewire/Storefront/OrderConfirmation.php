<?php

namespace App\Livewire\Storefront;

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class OrderConfirmation extends Component
{
    public Order $order;

    /**
     * Looked up by checkout_token rather than id or order number. The number is predictable
     * enough to be useful operationally, which is exactly why it must not be what grants access
     * to someone else's order and delivery address.
     */
    public function mount(string $order): void
    {
        $this->order = Order::query()
            ->where('checkout_token', $order)
            ->with(['items', 'shippingMethod'])
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.order-confirmation', [
            'transaction' => $this->order->payments()->latest('id')->first(),
        ]);
    }
}
