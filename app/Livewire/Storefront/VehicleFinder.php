<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\DecodesVin;
use App\Models\Category;
use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\CategoryIcons;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The three ways into a listing: pick the car, decode a VIN, or go one level narrower — a
 * collection's derivatives, or a category's subcategories.
 *
 * One component rather than three because they answer the same question and the customer should
 * be able to give up on one and try the next without the page moving underneath them. On a
 * collection the make is taken from the collection being viewed, so someone already looking at
 * Dacia is not asked which manufacturer they meant.
 */
class VehicleFinder extends Component
{
    use DecodesVin;

    public ?int $collectionId = null;

    public ?int $categoryId = null;

    /** Which panel is open, mirrored from Alpine so a server round trip does not close it. */
    public string $panel = '';

    public string $pickYear = '';

    public string $pickMakeId = '';

    public string $pickModelId = '';

    public string $pickGenerationId = '';

    public string $applied = '';

    /**
     * Either one, never both. A parameter left out can still arrive as an empty model, because
     * the container builds any class a method asks for, so both are read by their id.
     */
    public function mount(?VehicleCollection $collection = null, ?Category $category = null): void
    {
        $this->collectionId = $collection?->id;
        $this->categoryId = $collection?->id === null ? $category?->id : null;
        $this->pickMakeId = (string) ($collection?->make_id ?? '');
        $this->pickModelId = (string) ($collection?->model_id ?? '');
    }

    public function updatedPickMakeId(): void
    {
        $this->reset(['pickModelId', 'pickGenerationId']);
    }

    public function updatedPickModelId(): void
    {
        $this->pickGenerationId = '';
    }

    /** A different year can invalidate the model and the submodel under it. */
    public function updatedPickYear(): void
    {
        if ($this->pickModelId !== '' && ! $this->models()->contains('id', (int) $this->pickModelId)) {
            $this->reset(['pickModelId', 'pickGenerationId']);
        }

        if ($this->pickGenerationId !== '' && ! $this->generations()->contains('id', (int) $this->pickGenerationId)) {
            $this->pickGenerationId = '';
        }
    }

    /**
     * The submodel is optional. Plenty of owners know the make, the model and the year and have
     * no idea which generation that makes them, and refusing the selection until they do would
     * strand exactly the people this exists for.
     */
    public function applyPick(VehicleContext $context): void
    {
        // Re-checked here because the ids arrive from the client: a model that does not belong
        // to the chosen make would otherwise be stored and filter for the wrong car.
        $this->validate([
            'pickMakeId' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'pickModelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->where('make_id', $this->pickMakeId)],
            'pickGenerationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')->where('model_id', $this->pickModelId)],
            'pickYear' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ], [
            'pickMakeId.required' => 'Alege producătorul.',
            'pickModelId.required' => 'Alege modelul.',
            'pickModelId.exists' => 'Modelul ales nu aparține acestui producător.',
            'pickGenerationId.exists' => 'Submodelul ales nu aparține acestui model.',
        ]);

        $make = VehicleMake::query()->findOrFail($this->pickMakeId);
        $model = VehicleModel::query()->findOrFail($this->pickModelId);
        $generation = $this->pickGenerationId === '' ? null : VehicleGeneration::query()->find($this->pickGenerationId);

        $this->selectVehicle($context, new SelectedVehicle(
            makeId: (int) $make->id,
            makeName: (string) $make->name,
            modelId: (int) $model->id,
            modelName: (string) $model->name,
            generationId: $generation?->id,
            generationName: $generation?->name,
            year: $this->pickYear === '' ? null : (int) $this->pickYear,
        ));
    }

    public function render(VehicleContext $context)
    {
        $collection = $this->collectionId === null ? null : VehicleCollection::query()->find($this->collectionId);
        $category = $this->categoryId === null ? null : Category::query()->find($this->categoryId);

        return view('livewire.storefront.vehicle-finder', [
            'collection' => $collection,
            'category' => $category,
            'current' => $context->selection(),
            'makes' => $this->makes(),
            'years' => $this->years(),
            'models' => $this->models(),
            'generations' => $this->generations(),
            'choices' => $this->choices($collection, $category),
        ]);
    }

    protected function selectVehicle(VehicleContext $context, SelectedVehicle $vehicle): void
    {
        $context->select($vehicle);

        $this->applied = $vehicle->label();
        $this->vinCandidates = [];
        $this->vinMessage = '';

        // Two events: one the rest of the page already listens for, one the panel closes on.
        $this->dispatch('vehicle-changed');
        $this->dispatch('vehicle-picked');
    }

    /** A VIN that names only the make still starts the picker on it. */
    protected function prefillMake(int $makeId, ?int $modelYear): void
    {
        $this->pickMakeId = (string) $makeId;
        $this->reset(['pickModelId', 'pickGenerationId', 'pickYear']);
    }

    /**
     * Proving that a make has a real vehicle behind it means walking its models and generations,
     * and this list is identical for every visitor. An hour of staleness costs nothing; the scan
     * on every panel open would.
     *
     * @return EloquentCollection<int, VehicleMake>
     */
    private function makes(): EloquentCollection
    {
        return Cache::remember(
            'storefront:vehicle-picker:makes',
            now()->addHour(),
            fn (): EloquentCollection => VehicleMake::query()
                ->where('is_active', true)
                ->withConfigurations()
                ->orderBy('name')
                ->get(['id', 'name']),
        );
    }

    /** @return list<int> */
    private function years(): array
    {
        if ($this->pickMakeId === '') {
            return [];
        }

        return Cache::remember(
            'storefront:finder:years:'.$this->pickMakeId,
            now()->addHour(),
            fn (): array => VehicleConfiguration::query()
                ->whereNotNull('year')
                ->whereHas('generation.model', fn (Builder $q) => $q->where('make_id', $this->pickMakeId))
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn ($year): int => (int) $year)
                ->all(),
        );
    }

    /** @return EloquentCollection<int, VehicleModel> */
    private function models(): EloquentCollection
    {
        if ($this->pickMakeId === '') {
            return new EloquentCollection;
        }

        return VehicleModel::query()
            ->where('make_id', $this->pickMakeId)
            ->when(
                $this->pickYear !== '',
                fn (Builder $q) => $q->whereHas('generations.configurations', fn (Builder $c) => $c->where('year', $this->pickYear)),
                fn (Builder $q) => $q->withConfigurations(),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return EloquentCollection<int, VehicleGeneration> */
    private function generations(): EloquentCollection
    {
        if ($this->pickModelId === '') {
            return new EloquentCollection;
        }

        return VehicleGeneration::query()
            ->where('model_id', $this->pickModelId)
            ->when($this->pickYear !== '', fn (Builder $q) => $q
                ->whereHas('configurations', fn (Builder $c) => $c->where('year', $this->pickYear)))
            ->orderByDesc('year_from')
            ->get(['id', 'name', 'year_from', 'year_to']);
    }

    /**
     * One level narrower than the page: a collection's derivatives, or a category's
     * subcategories. Empty on a derivative's own page or a leaf category, where the third button
     * has nothing to offer and is not drawn.
     *
     * Real links, rendered server side, so they stay crawlable and work without JavaScript.
     *
     * @return Collection<int, array{name: string, url: string, image: ?string, icon: string, meta: ?string}>
     */
    private function choices(?VehicleCollection $collection, ?Category $category): Collection
    {
        if ($category !== null) {
            return $category->children()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'full_path', 'icon', 'image_path'])
                ->map(fn (Category $child): array => [
                    'name' => (string) $child->name,
                    'url' => route('storefront.category', $child),
                    'image' => $child->image_path ? Storage::disk('public')->url($child->image_path) : null,
                    'icon' => CategoryIcons::resolve($child->icon),
                    'meta' => null,
                ]);
        }

        if ($collection === null) {
            return collect();
        }

        return VehicleCollection::query()
            ->where('parent_id', $collection->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'square_image_path', 'year_from', 'year_to'])
            ->map(fn (VehicleCollection $child): array => [
                'name' => (string) $child->name,
                'url' => $child->url(),
                'image' => $child->squareImageUrl(),
                'icon' => 'car',
                'meta' => $child->yearRange(),
            ]);
    }
}
