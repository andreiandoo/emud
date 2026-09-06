<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogFitment;
use App\Models\VehicleConfiguration;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class VehicleDetail extends Component
{
    public VehicleConfiguration $vehicle;

    public function mount(VehicleConfiguration $vehicle): void
    {
        $this->vehicle = $vehicle->load(['generation.model.make', 'engine']);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.vehicle-detail', [
            'fitments' => CatalogFitment::query()
                ->with(['part.brand', 'part.category', 'constraints'])
                ->where('configuration_id', $this->vehicle->id)
                ->orderByDesc('confidence')
                ->paginate(50),
        ]);
    }
}
