<?php

namespace App\Livewire\Customer;

use App\Livewire\Concerns\DecodesVin;
use App\Models\CustomerVehicle;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\Garage as GarageService;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class Garage extends Component
{
    use DecodesVin;

    public ?int $editingId = null;

    public ?int $makeId = null;

    public ?int $modelId = null;

    public ?int $generationId = null;

    public string $nickname = '';

    public ?int $year = null;

    public string $registration_number = '';

    /** Set when the car was filled in from its VIN: the exact build, not just the model. */
    public ?int $configurationId = null;

    public function updatedMakeId(): void
    {
        $this->modelId = null;
        $this->generationId = null;
        $this->configurationId = null;
    }

    public function updatedModelId(): void
    {
        $this->generationId = null;
        $this->configurationId = null;
    }

    /** A different generation is a different build, so a decoded configuration no longer applies. */
    public function updatedGenerationId(): void
    {
        $this->configurationId = null;
    }

    public function edit(int $vehicleId): void
    {
        $vehicle = $this->ownedVehicle($vehicleId);

        $this->editingId = $vehicle->id;
        $this->makeId = $vehicle->make_id;
        $this->modelId = $vehicle->model_id;
        $this->generationId = $vehicle->generation_id;
        $this->nickname = (string) $vehicle->nickname;
        $this->year = $vehicle->year;
        $this->registration_number = (string) $vehicle->registration_number;
        $this->vin = (string) $vehicle->vin;
        $this->configurationId = $vehicle->configuration_id;
    }

    public function save(GarageService $garage): void
    {
        $data = $this->validate([
            'makeId' => ['required', 'integer', 'exists:vehicle_makes,id'],
            'modelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->where('make_id', $this->makeId)],
            'generationId' => ['nullable', 'integer', Rule::exists('vehicle_generations', 'id')->where('model_id', $this->modelId)],
            'nickname' => ['nullable', 'string', 'max:60'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'registration_number' => ['nullable', 'string', 'max:16'],
            'vin' => ['nullable', 'string', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
        ], [
            'vin.regex' => 'Seria de șasiu are 17 caractere și nu conține literele I, O sau Q.',
            'modelId.exists' => 'Modelul ales nu aparține acestei mărci.',
            'generationId.exists' => 'Generația aleasă nu aparține acestui model.',
        ]);

        $attributes = [
            'make_id' => $data['makeId'],
            'model_id' => $data['modelId'],
            'generation_id' => $data['generationId'] ?? null,
            'nickname' => $data['nickname'] ?: null,
            'year' => $data['year'],
            'registration_number' => $data['registration_number'] ?: null,
            'vin' => $this->vin !== '' ? mb_strtoupper($this->vin) : null,
            'configuration_id' => $this->configurationId,
        ];

        if ($this->editingId !== null) {
            $garage->update($this->ownedVehicle($this->editingId), $attributes);
        } else {
            $garage->add(auth()->user(), $attributes);
        }

        $this->resetForm();
    }

    public function makePrimary(int $vehicleId, GarageService $garage): void
    {
        $garage->makePrimary($this->ownedVehicle($vehicleId));
    }

    public function remove(int $vehicleId, GarageService $garage): void
    {
        $garage->remove($this->ownedVehicle($vehicleId));

        if ($this->editingId === $vehicleId) {
            $this->resetForm();
        }
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'makeId', 'modelId', 'generationId', 'nickname', 'year', 'registration_number', 'vin', 'vinMessage', 'vinCandidates', 'configurationId']);
        $this->resetValidation();
    }

    /**
     * A decoded VIN fills the form instead of choosing a car for the shop: the customer checks
     * what we found, adds a nickname if they like, and saves. The configuration travels with
     * it, which is what lets the vehicle page show the engine and the drive.
     */
    protected function selectVehicle(VehicleContext $context, SelectedVehicle $vehicle): void
    {
        $this->makeId = $vehicle->makeId;
        $this->modelId = $vehicle->modelId;
        $this->generationId = $vehicle->generationId;
        $this->configurationId = $vehicle->configurationId;
        $this->year = $vehicle->year ?? $this->year;
        $this->vinMessage = 'Am completat mașina din serie: '.$vehicle->label().'. Verifică datele și salveaz-o.';
    }

    /**
     * A VIN that names only the make still saves one dropdown. The model year fills the year only
     * when the customer has not typed one: the car's own year of manufacture is the better answer.
     */
    protected function prefillMake(int $makeId, ?int $modelYear): void
    {
        $this->makeId = $makeId;
        $this->modelId = null;
        $this->generationId = null;
        $this->configurationId = null;
        $this->year ??= $modelYear;
    }

    /**
     * Ids come from the rendered page, so ownership is re-checked on every action rather than
     * assumed: a customer must not be able to rename or delete a vehicle in someone else's
     * garage by editing the id in the request.
     */
    private function ownedVehicle(int $vehicleId): CustomerVehicle
    {
        return CustomerVehicle::query()
            ->where('user_id', auth()->id())
            ->findOrFail($vehicleId);
    }

    /** @return Collection<int, VehicleModel> */
    public function getModelsProperty(): Collection
    {
        return $this->makeId === null
            ? collect()
            : VehicleModel::query()->where('make_id', $this->makeId)->orderBy('name')->get();
    }

    /** @return Collection<int, VehicleGeneration> */
    public function getGenerationsProperty(): Collection
    {
        return $this->modelId === null
            ? collect()
            : VehicleGeneration::query()->where('model_id', $this->modelId)->orderByDesc('year_from')->get();
    }

    public function render(GarageService $garage)
    {
        return view('livewire.customer.garage', [
            'vehicles' => $garage->forUser(auth()->user()),
            'makes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->get(),
            'models' => $this->models,
            'generations' => $this->generations,
        ]);
    }
}
