<?php

namespace App\Livewire\Customer;

use App\Directory\NearbyShops;
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
    public function render(GarageService $garage, VehicleContext $context, NearbyShops $nearby)
    {
        $user = auth()->user();
        $vehicles = $garage->forUser($user);

        return view('livewire.customer.dashboard', [
            'vehicles' => $vehicles,
            'vehicle' => $context->current(),
            'recentOrders' => Order::query()->where('user_id', $user->id)->with('items')->latest('id')->limit(3)->get(),
            'orderCount' => Order::query()->where('user_id', $user->id)->count(),
            'appointments' => ServiceAppointment::query()->whereBelongsTo($user)->with(['shop', 'service'])->latest('id')->limit(3)->get(),
            'favouriteCount' => WishlistItem::query()->where('user_id', $user->id)->count(),
            'location' => $nearby->locationOf($user),
            'nearby' => $nearby->forUser($user, $vehicles->firstWhere('is_primary', true) ?? $vehicles->first()),
        ]);
    }
}
