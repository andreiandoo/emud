<?php

namespace App\Livewire\Customer;

use App\Models\CustomerVehicle;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\Garage as GarageService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Garage extends Component
{
    public ?int $editingId = null;

    public ?int $makeId = null;

    public ?int $modelId = null;

    public ?int $generationId = null;

    public string $nickname = '';

    public ?int $year = null;

    public string $registration_number = '';

    public function updatedMakeId(): void
    {
        $this->modelId = null;
        $this->generationId = null;
    }

    public function updatedModelId(): void
    {
        $this->generationId = null;
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
        ], [
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
        $this->reset(['editingId', 'makeId', 'modelId', 'generationId', 'nickname', 'year', 'registration_number']);
        $this->resetValidation();
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
