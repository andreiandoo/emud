<?php

namespace App\Livewire\Customer;

use App\Models\Order;
use App\Models\ServiceAppointment;
use App\Models\WishlistItem;
use App\Storefront\Garage as GarageService;
use App\Storefront\VehicleContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class Dashboard extends Component
{
    public function render(GarageService $garage, VehicleContext $context)
    {
        $user = auth()->user();

        return view('livewire.customer.dashboard', [
            'vehicles' => $garage->forUser($user),
            'vehicle' => $context->current(),
            'recentOrders' => Order::query()->where('user_id', $user->id)->with('items')->latest('id')->limit(3)->get(),
            'orderCount' => Order::query()->where('user_id', $user->id)->count(),
            'appointments' => ServiceAppointment::query()->whereBelongsTo($user)->with(['shop', 'service'])->latest('id')->limit(3)->get(),
            'favouriteCount' => WishlistItem::query()->where('user_id', $user->id)->count(),
        ]);
    }
}
