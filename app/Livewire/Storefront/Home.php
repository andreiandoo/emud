<?php

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Review;
use App\Storefront\VehicleContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Home extends Component
{
    #[On('vehicle-changed')]
    public function refreshVehicle(): void
    {
        // The picker owns the selection; the page re-renders so the header chip and the
        // vehicle-aware sections reflect it without a full navigation.
    }

    public function render(VehicleContext $context)
    {
        return view('livewire.storefront.home', [
            'vehicle' => $context->current(),
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('is_visible_in_menu', true)
                ->orderBy('position')
                ->orderBy('name')
                ->limit(12)
                ->get(),
            'reviews' => Review::query()
                ->published()
                ->where('is_featured', true)
                ->ordered()
                ->limit(3)
                ->get(),
        ]);
    }
}
