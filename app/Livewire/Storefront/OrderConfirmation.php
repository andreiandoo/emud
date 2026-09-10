<?php

namespace App\Livewire\Storefront;

use App\Models\Order;
use App\Models\ServiceShop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class OrderConfirmation extends Component
{
    public Order $order;

    /**
     * Looked up by checkout_token rather than id or order number. The number is predictable
     * enough to be useful operationally, which is exactly why it must not be what grants access
     * to someone else's order and delivery address.
     *
     * The route parameter is deliberately not called "order": matching the typed Order property
     * makes implicit binding try to resolve the token as a primary key before mount() runs.
     */
    public function mount(string $token): void
    {
        $this->order = Order::query()
            ->where('checkout_token', $token)
            ->with(['items', 'shippingMethod', 'shippingAddress'])
            ->firstOrFail();
    }

    /**
     * Workshops that will fit what was just bought, nearest first by the only measure available
     * here: the town the parcel is going to. Shown on this page because it is the one moment the
     * customer is certain to be thinking about the job.
     *
     * @return EloquentCollection<int, ServiceShop>
     */
    private function fitters(): EloquentCollection
    {
        $city = $this->order->shippingAddress?->city;

        if ($city === null) {
            return new EloquentCollection;
        }

        return ServiceShop::query()
            ->published()
            ->with('hours')
            ->where('fits_parts_bought_here', true)
            ->whereRaw('lower(city) = ?', [mb_strtolower($city)])
            ->promotedFirst()
            ->orderBy('name')
            ->limit(3)
            ->get();
    }

    public function render()
    {
        return view('livewire.storefront.order-confirmation', [
            'transaction' => $this->order->payments()->latest('id')->first(),
            'fitters' => $this->fitters(),
        ]);
    }
}
