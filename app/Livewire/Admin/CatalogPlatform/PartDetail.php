<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogPart;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class PartDetail extends Component
{
    public CatalogPart $part;

    public function mount(CatalogPart $part): void
    {
        $this->part = $part->load([
            'brand',
            'category',
            'numbers.oeMake',
            'numbers.source',
            'fitments.configuration.generation.model.make',
            'fitments.constraints',
            'outgoingRelations.targetPart.brand',
            'outgoingRelations.source',
            'incomingRelations.sourcePart.brand',
            'incomingRelations.source',
            'unresolvedRelations.source',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.catalog-platform.part-detail');
    }
}
