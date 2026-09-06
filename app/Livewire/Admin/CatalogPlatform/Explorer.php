<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\VehicleConfiguration;
use App\Models\VehicleIdentifier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class Explorer extends Component
{
    #[Url]
    public string $mode = 'everything';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $make = null;

    #[Url]
    public ?int $model = null;

    #[Url]
    public ?int $category = null;

    #[Url]
    public ?int $brand = null;

    public function render()
    {
        $term = trim($this->search);

        $vehicles = collect();
        $parts = collect();
        $numbers = collect();

        if ($term !== '' && in_array($this->mode, ['everything', 'vehicles'], true)) {
            $vehicles = VehicleConfiguration::query()
                ->with(['generation.model.make', 'engine'])
                ->where(function ($query) use ($term): void {
                    $query->where('commercial_name', 'ilike', "%{$term}%")
                        ->orWhere('eu_type_approval', 'ilike', "%{$term}%")
                        ->orWhereHas('generation.model', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('generation.model.make', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('engine', fn ($q) => $q->where('engine_code', 'ilike', "%{$term}%"));
                })
                ->when($this->make, fn ($q) => $q->whereHas('generation.model', fn ($q) => $q->where('make_id', $this->make)))
                ->when($this->model, fn ($q) => $q->whereHas('generation', fn ($q) => $q->where('model_id', $this->model)))
                ->limit(50)
                ->get();
        }

        if ($term !== '' && in_array($this->mode, ['everything', 'parts'], true)) {
            $parts = CatalogPart::query()
                ->with(['brand', 'category'])
                ->where(function ($query) use ($term): void {
                    $query->where('mpn_normalized', 'ilike', "%{$term}%")
                        ->orWhere('name', 'ilike', "%{$term}%")
                        ->orWhereHas('brand', fn ($q) => $q->where('name', 'ilike', "%{$term}%"));
                })
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->when($this->brand, fn ($q) => $q->where('brand_id', $this->brand))
                ->limit(50)
                ->get();
        }

        if ($term !== '' && in_array($this->mode, ['everything', 'numbers'], true)) {
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $term) ?? $term);
            $numbers = CatalogPartNumber::query()
                ->with(['part.brand', 'oeMake'])
                ->where(function ($query) use ($term, $normalized): void {
                    $query->where('number_raw', 'ilike', "%{$term}%")
                        ->orWhere('number_normalized', 'ilike', "%{$term}%")
                        ->orWhere('number_compact', 'ilike', "%{$normalized}%");
                })
                ->limit(50)
                ->get();
        }

        if ($term !== '' && $this->mode === 'identifiers') {
            $numbers = VehicleIdentifier::query()
                ->with('configuration.generation.model.make')
                ->where('value_normalized', 'ilike', "%{$term}%")
                ->limit(50)
                ->get();
        }

        return view('livewire.admin.catalog-platform.explorer', compact('vehicles', 'parts', 'numbers'));
    }
}
