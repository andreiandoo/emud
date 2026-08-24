<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Attribute;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class AttributesIndex extends Component
{
    public function render()
    {
        return view('livewire.admin.catalog.attributes-index', [
            'attributes' => Attribute::query()->withCount(['categories', 'options'])->orderBy('name')->get(),
        ]);
    }
}
