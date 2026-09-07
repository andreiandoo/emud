<?php

namespace App\Livewire\Customer;

use App\Storefront\Garage as GarageService;
use App\Storefront\VehicleContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Dashboard extends Component
{
    public function render(GarageService $garage, VehicleContext $context)
    {
        return view('livewire.customer.dashboard', [
            'vehicles' => $garage->forUser(auth()->user()),
            'vehicle' => $context->current(),
        ]);
    }
}
