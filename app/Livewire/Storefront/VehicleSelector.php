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
use Illuminate\Database\Eloquent\Builder;
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
 * A customer with several cars can tick more than one, and listings and search then show what
 * fits any of them. Cars leave the garage from the garage page only, so nothing here removes
 * one: unticking narrows the search, and at least one car stays ticked.
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

    /** What a VIN decoded here found, said in the panel instead of closing it without a word. */
    public string $vinFound = '';

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

    /** A new VIN being typed makes the last answer stale. */
    public function updatedVin(): void
    {
        $this->vinFound = '';
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

    /** Only this car: what picking one out of a single-car garage means. */
    public function chooseFromGarage(int $vehicleId, VehicleContext $context): void
    {
        $vehicle = $this->ownedVehicle($vehicleId);

        $context->select(SelectedVehicle::fromCustomerVehicle($vehicle));
        $this->syncFromContext();

        $this->dispatch('vehicle-changed');
    }

    /**
     * Ticks or unticks one car of the garage. The last ticked car stays ticked: an empty choice
     * would mean "no car", and showing the whole catalogue is what the switch is for.
     */
    public function toggleFromGarage(int $vehicleId, VehicleContext $context, Garage $garage): void
    {
        $vehicle = $this->ownedVehicle($vehicleId);
        $selection = $context->selection();

        // A car picked from the dropdowns is not one of the garage's; ticking a garage car starts
        // a new choice rather than mixing the two.
        $ticked = $selection !== null && $selection->isFromGarage() ? $selection->garageIds() : [];

        if (in_array($vehicle->id, $ticked, true)) {
            if (count($ticked) === 1) {
                return;
            }

            $ticked = array_values(array_diff($ticked, [$vehicle->id]));
        } else {
            $ticked[] = $vehicle->id;
        }

        $this->chooseGarageCars($context, $garage, $ticked);
    }

    public function chooseAllFromGarage(VehicleContext $context, Garage $garage): void
    {
        abort_if(Auth::user() === null, 403);

        $this->chooseGarageCars($context, $garage, $garage->forUser(Auth::user())->modelKeys());
    }

    /** A VIN that names only the make still starts the dropdowns on it. */
    protected function prefillMake(int $makeId, ?int $modelYear): void
    {
        $this->makeId = $makeId;
        $this->modelId = null;
        $this->generationId = null;
    }

    /**
     * A VIN decoded here selects the car the way the dropdowns would, and says what it found.
     * When it names a car already in the garage, that car is the one selected: it carries the
     * year, the plate and the history the decoded model does not. The panel stays open on the
     * answer — closing it silently looked, to a customer, as though nothing had happened.
     */
    protected function selectVehicle(VehicleContext $context, SelectedVehicle $vehicle): void
    {
        $own = $this->garageMatch($vehicle);

        $context->select($own === null ? $vehicle : SelectedVehicle::fromCustomerVehicle($own));
        $this->syncFromContext();
        $this->vin = '';
        $this->vinFound = $own === null
            ? 'Am identificat '.$vehicle->label().'. Căutăm piese pentru ea.'
            : 'E mașina din garajul tău: '.($own->nickname ?: $own->label()).'. Căutăm piese pentru ea.';

        $this->dispatch('vehicle-decoded');
        $this->dispatch('vehicle-changed');
    }

    /** The shop-wide switch between "only what fits my car" and everything. */
    public function toggleFilter(VehicleContext $context): void
    {
        $context->setFiltersParts(! $context->filtersParts());

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
        $selection = $context->selection();

        return view('livewire.storefront.vehicle-selector', [
            'selection' => $selection,
            'tickedIds' => $selection !== null && $selection->isFromGarage() ? $selection->garageIds() : [],
            'filtersParts' => $context->filtersParts(),
            'garageVehicles' => $user === null ? new EloquentCollection : $garage->forUser($user),
            'makes' => $this->selectableMakes(),
            'models' => $this->models,
            'generations' => $this->generations,
        ]);
    }

    /**
     * The chosen cars in the garage's own order, primary first, so the car single-car answers
     * speak about does not depend on which box was ticked first.
     *
     * @param  list<int>  $ids
     */
    private function chooseGarageCars(VehicleContext $context, Garage $garage, array $ids): void
    {
        $context->selectMany($garage->forUser(Auth::user())
            ->whereIn('id', $ids)
            ->map(fn (CustomerVehicle $vehicle): SelectedVehicle => SelectedVehicle::fromCustomerVehicle($vehicle))
            ->values()
            ->all());

        $this->syncFromContext();

        $this->dispatch('vehicle-changed');
    }

    /** The signed-in customer's car of the same make and model, and generation when both know it. */
    private function garageMatch(SelectedVehicle $vehicle): ?CustomerVehicle
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        return CustomerVehicle::query()
            ->whereBelongsTo($user)
            ->where('make_id', $vehicle->makeId)
            ->where('model_id', $vehicle->modelId)
            ->when($vehicle->generationId !== null, fn (Builder $query) => $query
                ->where(fn (Builder $same) => $same->whereNull('generation_id')->orWhere('generation_id', $vehicle->generationId)))
            ->with(['make', 'model', 'generation'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /**
     * Scoped to the signed-in customer rather than resolved by id alone: the id comes from the
     * browser, and another customer's plate and service history hang off it.
     */
    private function ownedVehicle(int $vehicleId): CustomerVehicle
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        return CustomerVehicle::query()
            ->whereBelongsTo($user)
            ->with(['make', 'model', 'generation'])
            ->findOrFail($vehicleId);
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
