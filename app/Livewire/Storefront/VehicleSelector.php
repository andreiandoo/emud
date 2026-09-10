<?php

namespace App\Livewire\Storefront;

use App\Livewire\Concerns\DecodesVin;
use App\Models\CustomerVehicle;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\Garage;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The header's vehicle widget.
 *
 * It has two faces on purpose. A signed-in customer picks from the cars they already saved,
 * because that is one click instead of three dropdowns they have filled in before. Everyone
 * else gets the make/model/generation cascade, plus the reason to make an account stated where
 * the benefit is obvious rather than as a generic banner.
 *
 * The widget lives in the layout, so it renders on every storefront page. Anything it queries
 * on a cold render is therefore paid for site-wide, which is why the make list is cached and
 * the models and generations load only once a make has been chosen.
 */
class VehicleSelector extends Component
{
    use DecodesVin;

    public ?int $makeId = null;

    public ?int $modelId = null;

    public ?int $generationId = null;

    public function mount(): void
    {
        $this->syncFromContext();
    }

    /**
     * Kept in step with selections made elsewhere — the picker on the home page, or the garage
     * setting a different primary car — so the header never shows a vehicle the rest of the
     * page is not filtering by.
     *
     * Resolved from the container rather than injected: Livewire passes an event's payload to a
     * listener's parameters, so a type-hinted dependency here would be filled with event data.
     */
    #[On('vehicle-changed')]
    public function syncFromContext(): void
    {
        $current = app(VehicleContext::class)->current();

        $this->makeId = $current?->makeId;
        $this->modelId = $current?->modelId;
        $this->generationId = $current?->generationId;
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

    /** Picks one of the customer's own cars, which is the one-click path the garage exists for. */
    public function chooseFromGarage(int $vehicleId, VehicleContext $context): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        $vehicle = CustomerVehicle::query()
            // Scoped to the signed-in customer rather than resolved by id alone: the id comes
            // from the browser, and another customer's plate and service history hang off it.
            ->whereBelongsTo($user)
            ->with(['make', 'model', 'generation'])
            ->findOrFail($vehicleId);

        $context->select(SelectedVehicle::fromCustomerVehicle($vehicle));
        $this->syncFromContext();

        $this->dispatch('vehicle-changed');
    }

    /** A VIN decoded here selects the car exactly as the dropdowns would, then clears the form. */
    protected function selectVehicle(VehicleContext $context, SelectedVehicle $vehicle): void
    {
        $context->select($vehicle);
        $this->syncFromContext();
        $this->vin = '';

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

    public function render(VehicleContext $context, Garage $garage)
    {
        $user = Auth::user();

        return view('livewire.storefront.vehicle-selector', [
            'selected' => $context->current(),
            'garageVehicles' => $user === null ? new EloquentCollection : $garage->forUser($user),
            'makes' => $this->selectableMakes(),
            'models' => $this->models,
            'generations' => $this->generations,
        ]);
    }

    /**
     * Proving that a make has any vehicle behind it means walking its models and generations.
     * The list only changes when a catalogue import is canonicalized, so it is worth an hour of
     * staleness rather than that scan on every page view of the site.
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
}
