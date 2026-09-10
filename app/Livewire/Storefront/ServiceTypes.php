<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceCategory;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class ServiceTypes extends Component
{
    public function render()
    {
        return view('livewire.storefront.service-types', [
            'categories' => ServiceCategory::query()
                ->with(['services' => fn ($query) => $query->active()->withCount('shops')])
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
