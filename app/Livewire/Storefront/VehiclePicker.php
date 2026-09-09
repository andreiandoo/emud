<?php

namespace App\Livewire\Storefront;

use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Component;

class VehiclePicker extends Component
{
    public ?int $makeId = null;

    public ?int $modelId = null;

    public ?int $generationId = null;

    public function mount(VehicleContext $context): void
    {
        $current = $context->current();

        if ($current === null) {
            return;
        }

        $this->makeId = $current->makeId;
        $this->modelId = $current->modelId;
        $this->generationId = $current->generationId;
    }

    public function updatedMakeId(): void
    {
        $this->modelId = null;
        $this->generationId = null;
    }

    public function updatedModelId(): void
    {
        $this->generationId = null;
    }

    /**
     * A generation is optional: plenty of customers know their make and model but not which
     * generation they own, and refusing the selection until they do would strand them.
     */
    public function apply(VehicleContext $context): void
    {
        // The ids arrive from the client, so the cascade is re-checked here: a model that does
        // not belong to the chosen make would otherwise be stored and filter the wrong parts.
        $this->validate([
            'makeId' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'modelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->where('make_id', $this->makeId)],
            'generationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')->where('model_id', $this->modelId)],
        ], [
            'makeId.required' => 'Alege marca mașinii.',
            'modelId.required' => 'Alege modelul mașinii.',
            'modelId.exists' => 'Modelul ales nu aparține acestei mărci.',
            'generationId.exists' => 'Generația aleasă nu aparține acestui model.',
        ]);

        $make = VehicleMake::query()->findOrFail($this->makeId);
        $model = VehicleModel::query()->findOrFail($this->modelId);
        $generation = $this->generationId === null ? null : VehicleGeneration::query()->find($this->generationId);

        $context->select(new SelectedVehicle(
            makeId: (int) $make->id,
            makeName: (string) $make->name,
            modelId: (int) $model->id,
            modelName: (string) $model->name,
            generationId: $generation?->id,
            generationName: $generation?->name,
        ));

        $this->dispatch('vehicle-changed');
    }

    public function clear(VehicleContext $context): void
    {
        $context->clear();

        $this->makeId = null;
        $this->modelId = null;
        $this->generationId = null;

        $this->dispatch('vehicle-changed');
    }

    /** @return Collection<int, VehicleModel> */
    public function getModelsProperty(): Collection
    {
        return $this->makeId === null
            ? collect()
            : VehicleModel::query()
                ->where('make_id', $this->makeId)
                ->withConfigurations()
                ->orderBy('name')
                ->get();
    }

    /** @return Collection<int, VehicleGeneration> */
    public function getGenerationsProperty(): Collection
    {
        return $this->modelId === null
            ? collect()
            : VehicleGeneration::query()->where('model_id', $this->modelId)->orderByDesc('year_from')->get();
    }

    /**
     * The picker renders on every storefront page, and proving that a make has no vehicle
     * behind it means walking all of its models and generations. The list only changes when a
     * catalogue import is canonicalized, so it is worth an hour of staleness rather than that
     * scan on every page view.
     *
     * @return Collection<int, VehicleMake>
     */
    private function selectableMakes(): Collection
    {
        return Cache::remember(
            'storefront:vehicle-picker:makes',
            now()->addHour(),
            fn (): Collection => VehicleMake::query()
                ->where('is_active', true)
                ->withConfigurations()
                ->orderBy('name')
                ->get(['id', 'name']),
        );
    }

    public function render()
    {
        return view('livewire.storefront.vehicle-picker', [
            'makes' => $this->selectableMakes(),
            'models' => $this->models,
            'generations' => $this->generations,
        ]);
    }
}
