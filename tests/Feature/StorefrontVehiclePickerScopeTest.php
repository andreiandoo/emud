<?php

namespace Tests\Feature;

use App\Livewire\Storefront\VehiclePicker;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontVehiclePickerScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The make list is cached across renders; a stale entry would hide what each case sets up.
        Cache::forget('storefront:vehicle-picker:makes');
    }

    /**
     * vPIC registers every US vehicle manufacturer, welding shops and trailer builders
     * included, as reference data for VIN decoding. That is 12,275 of 13,138 makes with no
     * vehicle behind them, and they were all being offered to customers as choices.
     */
    public function test_a_make_with_no_vehicle_behind_it_is_not_offered(): void
    {
        $this->makeWithVehicle('Suzuki', 'Jimny');
        VehicleMake::create(['name' => 'Austin Welding Service', 'slug' => 'austin-welding-service', 'is_active' => true]);

        Livewire::test(VehiclePicker::class)
            ->assertSee('Suzuki')
            ->assertDontSee('Austin Welding Service');
    }

    /**
     * A make can be real while some of its models are reference-only, so the same rule has to
     * apply one level down or the model dropdown fills with empty vPIC entries.
     */
    public function test_a_model_with_no_vehicle_behind_it_is_not_offered(): void
    {
        $make = $this->makeWithVehicle('Toyota', 'Hilux');
        VehicleModel::create(['make_id' => $make->id, 'name' => 'Stout', 'slug' => 'stout']);

        Livewire::test(VehiclePicker::class)
            ->set('makeId', $make->id)
            ->assertSee('Hilux')
            ->assertDontSee('Stout');
    }

    private function makeWithVehicle(string $makeName, string $modelName): VehicleMake
    {
        $make = VehicleMake::create(['name' => $makeName, 'slug' => strtolower($makeName), 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => $modelName, 'slug' => strtolower($modelName)]);
        $generation = VehicleGeneration::create(['model_id' => $model->id, 'name' => 'I', 'year_from' => 2018]);
        VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2020]);

        return $make;
    }
}
