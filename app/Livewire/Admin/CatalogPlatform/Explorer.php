<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Catalog\Normalization\IdentifierNormalizer;
use App\Models\Brand;
use App\Models\CatalogConflict;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\Category;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleIdentifier;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class Explorer extends Component
{
    #[Url] public string $mode = 'everything';
    #[Url] public string $search = '';
    #[Url] public ?int $make = null;
    #[Url] public ?int $model = null;
    #[Url] public ?int $generation = null;
    #[Url] public ?int $year = null;
    #[Url] public string $fuel = '';
    #[Url] public ?int $category = null;
    #[Url] public ?int $brand = null;
    #[Url] public ?int $source = null;
    #[Url] public string $position = '';
    #[Url] public ?float $minConfidence = null;

    public function updatedMake(): void
    {
        $this->model = null;
        $this->generation = null;
    }

    public function updatedModel(): void
    {
        $this->generation = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['make', 'model', 'generation', 'year', 'fuel', 'category', 'brand', 'source', 'position', 'minConfidence']);
    }

    public function render(IdentifierNormalizer $normalizer)
    {
        $term = trim($this->search);
        $hasVehicleFilter = $this->make || $this->model || $this->generation || $this->year || $this->fuel !== '';
        $hasPartFilter = $this->category || $this->brand;
        $hasTechnicalFilter = $this->source || $this->position !== '' || $this->minConfidence !== null;
        $shouldQuery = $term !== '' || $hasVehicleFilter || $hasPartFilter || $hasTechnicalFilter;

        $vehicles = collect();
        $parts = collect();
        $numbers = collect();
        $fitments = collect();
        $sourcesFound = collect();
        $conflicts = collect();

        if ($shouldQuery && in_array($this->mode, ['everything', 'vehicles'], true)) {
            $vehicles = VehicleConfiguration::query()
                ->with(['generation.model.make', 'engine'])
                ->when($term !== '', function ($query) use ($term): void {
                    $query->where(function ($query) use ($term): void {
                        $query->where('commercial_name', 'ilike', "%{$term}%")
                            ->orWhere('eu_type_approval', 'ilike', "%{$term}%")
                            ->orWhere('eu_type', 'ilike', "%{$term}%")
                            ->orWhere('eu_variant', 'ilike', "%{$term}%")
                            ->orWhere('eu_version', 'ilike', "%{$term}%")
                            ->orWhereHas('generation.model', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                            ->orWhereHas('generation.model.make', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                            ->orWhereHas('engine', fn ($q) => $q->where('engine_code', 'ilike', "%{$term}%")->orWhere('name', 'ilike', "%{$term}%"));
                    });
                })
                ->when($this->make, fn ($q) => $q->whereHas('generation.model', fn ($q) => $q->where('make_id', $this->make)))
                ->when($this->model, fn ($q) => $q->whereHas('generation', fn ($q) => $q->where('model_id', $this->model)))
                ->when($this->generation, fn ($q) => $q->where('generation_id', $this->generation))
                ->when($this->year, fn ($q) => $q->where(fn ($q) => $q->where('year', $this->year)->orWhere(fn ($q) => $q->where('model_year_from', '<=', $this->year)->where(fn ($q) => $q->whereNull('model_year_to')->orWhere('model_year_to', '>=', $this->year)))))
                ->when($this->fuel !== '', fn ($q) => $q->where('fuel_type', $this->fuel))
                ->when($this->source, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('catalog_source_assertions')->whereColumn('catalog_source_assertions.entity_id', 'vehicle_configurations.id')->where('catalog_source_assertions.entity_type', 'vehicle_configuration')->where('catalog_source_assertions.catalog_source_id', $this->source)))
                ->orderBy('year')
                ->limit(100)
                ->get();
        }

        if ($shouldQuery && in_array($this->mode, ['everything', 'parts'], true)) {
            $parts = CatalogPart::query()
                ->with(['brand', 'category'])
                ->when($term !== '', function ($query) use ($term, $normalizer): void {
                    $compact = $normalizer->compact($term);
                    $query->where(function ($query) use ($term, $compact): void {
                        $query->where('mpn_normalized', 'ilike', "%{$term}%")
                            ->orWhere('name', 'ilike', "%{$term}%")
                            ->orWhereHas('brand', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                            ->orWhereHas('numbers', fn ($q) => $q->where('number_raw', 'ilike', "%{$term}%")->orWhere('number_compact', 'ilike', "%{$compact}%"));
                    });
                })
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->when($this->brand, fn ($q) => $q->where('brand_id', $this->brand))
                ->when($this->source, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('catalog_source_assertions')->whereColumn('catalog_source_assertions.entity_id', 'catalog_parts.id')->where('catalog_source_assertions.entity_type', 'catalog_part')->where('catalog_source_assertions.catalog_source_id', $this->source)))
                ->when($this->make || $this->model || $this->generation || $this->year || $this->fuel !== '', fn ($q) => $q->whereHas('fitments.configuration', function ($q): void {
                    $q->when($this->make, fn ($q) => $q->whereHas('generation.model', fn ($q) => $q->where('make_id', $this->make)))
                        ->when($this->model, fn ($q) => $q->whereHas('generation', fn ($q) => $q->where('model_id', $this->model)))
                        ->when($this->generation, fn ($q) => $q->where('generation_id', $this->generation))
                        ->when($this->year, fn ($q) => $q->where('year', $this->year))
                        ->when($this->fuel !== '', fn ($q) => $q->where('fuel_type', $this->fuel));
                }))
                ->limit(100)
                ->get();
        }

        if ($term !== '' && $this->mode === 'numbers') {
            $compact = $normalizer->compact($term);
            $normalized = $normalizer->normalize($term);
            $numbers = CatalogPartNumber::query()
                ->with(['part.brand', 'oeMake', 'source'])
                ->where(fn ($q) => $q->where('number_normalized', 'ilike', "%{$normalized}%")->orWhere('number_compact', 'ilike', "%{$compact}%"))
                ->when($this->source, fn ($q) => $q->where('catalog_source_id', $this->source))
                ->orderByRaw('CASE WHEN number_compact = ? THEN 0 WHEN number_normalized = ? THEN 1 ELSE 2 END', [$compact, $normalized])
                ->limit(100)
                ->get();
        }

        if ($term !== '' && $this->mode === 'identifiers') {
            $numbers = VehicleIdentifier::query()
                ->with(['configuration.generation.model.make', 'source'])
                ->where(fn ($q) => $q->where('value_raw', 'ilike', "%{$term}%")->orWhere('value_normalized', 'ilike', "%{$term}%"))
                ->when($this->source, fn ($q) => $q->where('catalog_source_id', $this->source))
                ->limit(100)
                ->get();
        }

        if ($shouldQuery && $this->mode === 'fitments') {
            $fitments = CatalogFitment::query()
                ->with(['part.brand', 'part.category', 'configuration.generation.model.make', 'configuration.engine', 'source', 'constraints'])
                ->when($term !== '', function ($q) use ($term): void {
                    $q->where(fn ($q) => $q->whereHas('part', fn ($q) => $q->where('mpn_normalized', 'ilike', "%{$term}%")->orWhere('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('part.brand', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('configuration.generation.model', fn ($q) => $q->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('configuration.generation.model.make', fn ($q) => $q->where('name', 'ilike', "%{$term}%")));
                })
                ->when($this->make, fn ($q) => $q->whereHas('configuration.generation.model', fn ($q) => $q->where('make_id', $this->make)))
                ->when($this->model, fn ($q) => $q->whereHas('configuration.generation', fn ($q) => $q->where('model_id', $this->model)))
                ->when($this->generation, fn ($q) => $q->whereHas('configuration', fn ($q) => $q->where('generation_id', $this->generation)))
                ->when($this->year, fn ($q) => $q->whereHas('configuration', fn ($q) => $q->where('year', $this->year)))
                ->when($this->fuel !== '', fn ($q) => $q->whereHas('configuration', fn ($q) => $q->where('fuel_type', $this->fuel)))
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->when($this->brand, fn ($q) => $q->whereHas('part', fn ($q) => $q->where('brand_id', $this->brand)))
                ->when($this->source, fn ($q) => $q->where('catalog_source_id', $this->source))
                ->when($this->position !== '', fn ($q) => $q->where('position', $this->position))
                ->when($this->minConfidence !== null, fn ($q) => $q->where('confidence', '>=', $this->minConfidence))
                ->orderByDesc('confidence')
                ->limit(100)
                ->get();
        }

        if ($this->mode === 'sources') {
            $sourcesFound = CatalogSource::query()
                ->withCount(['records', 'importRuns'])
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$term}%")->orWhere('code', 'ilike', "%{$term}%")->orWhere('source_type', 'ilike', "%{$term}%")))
                ->orderBy('name')->limit(100)->get();
        }

        if ($this->mode === 'conflicts') {
            $conflicts = CatalogConflict::query()
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('entity_type', 'ilike', "%{$term}%")->orWhere('field_or_relation', 'ilike', "%{$term}%")))
                ->when($this->source, fn ($q) => $q->whereRaw('assertion_ids::text ILIKE ?', ['%'.$this->source.'%']))
                ->latest()->limit(100)->get();
        }

        $makes = VehicleMake::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $models = $this->make ? VehicleModel::query()->where('make_id', $this->make)->orderBy('name')->get(['id', 'name']) : collect();
        $generations = $this->model ? VehicleGeneration::query()->where('model_id', $this->model)->orderBy('year_from')->get(['id', 'name', 'year_from', 'year_to']) : collect();
        $categories = Category::query()->where('is_active', true)->orderBy('full_path')->get(['id', 'name', 'full_path']);
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $sources = CatalogSource::query()->orderBy('name')->get(['id', 'name', 'code']);
        $fuels = VehicleConfiguration::query()->whereNotNull('fuel_type')->distinct()->orderBy('fuel_type')->pluck('fuel_type');
        $positions = CatalogFitment::query()->whereNotNull('position')->distinct()->orderBy('position')->pluck('position');

        return view('livewire.admin.catalog-platform.explorer', compact(
            'vehicles', 'parts', 'numbers', 'fitments', 'sourcesFound', 'conflicts',
            'makes', 'models', 'generations', 'categories', 'brands', 'sources', 'fuels', 'positions', 'shouldQuery'
        ));
    }
}
