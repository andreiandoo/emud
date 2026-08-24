<?php

namespace App\Livewire\Admin;

use App\Models\VehicleMake;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class VehiclesIndex extends Component
{
    #[Url]
    public string $search = '';

    public function render()
    {
        return view('livewire.admin.vehicles-index', [
            'makes' => VehicleMake::query()
                ->withCount('models')
                ->with(['models' => fn ($query) => $query->withCount('generations')->orderBy('name')])
                ->when($this->search, fn ($query) => $query->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')->get(),
        ]);
    }
}
