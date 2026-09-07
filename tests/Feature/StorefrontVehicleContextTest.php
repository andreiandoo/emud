<?php

namespace Tests\Feature;

use App\Livewire\Storefront\VehiclePicker;
use App\Models\CustomerVehicle;
use App\Models\User;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontVehicleContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_without_a_selection_has_no_vehicle(): void
    {
        $this->assertNull(app(VehicleContext::class)->current());
    }

    public function test_a_selection_is_remembered_for_the_visit(): void
    {
        $context = app(VehicleContext::class);
        $context->select(new SelectedVehicle(1, 'Dacia', 2, 'Duster'));

        $this->assertSame('Dacia Duster', $context->current()?->label());
    }

    public function test_a_signed_in_customer_gets_their_primary_garage_vehicle(): void
    {
        $user = User::factory()->create();
        $this->garageVehicle($user, 'Suzuki', 'Jimny', primary: false);
        $this->garageVehicle($user, 'Toyota', 'Hilux', primary: true);

        $this->actingAs($user);

        $current = app(VehicleContext::class)->current();

        $this->assertSame('Toyota Hilux', $current?->label());
        $this->assertTrue($current->isFromGarage());
    }

    /**
     * Clearing has to outrank the garage. Forgetting the session entry instead would make the
     * button do nothing for the customers who own a garage in the first place.
     */
    public function test_clearing_beats_the_garage_for_a_signed_in_customer(): void
    {
        $user = User::factory()->create();
        $this->garageVehicle($user, 'Jeep', 'Wrangler', primary: true);
        $this->actingAs($user);

        $context = app(VehicleContext::class);
        $this->assertNotNull($context->current());

        $context->clear();

        $this->assertNull($context->current());
    }

    public function test_resetting_hands_the_choice_back_to_the_garage(): void
    {
        $user = User::factory()->create();
        $this->garageVehicle($user, 'Jeep', 'Wrangler', primary: true);
        $this->actingAs($user);

        $context = app(VehicleContext::class);
        $context->clear();
        $context->resetToGarage();

        $this->assertSame('Jeep Wrangler', $context->current()?->label());
    }

    /**
     * Nothing in the schema enforces a single primary vehicle, so resolution must stay
     * deterministic rather than depending on row order when two are flagged.
     */
    public function test_resolution_is_deterministic_when_two_vehicles_are_flagged_primary(): void
    {
        $user = User::factory()->create();
        $first = $this->garageVehicle($user, 'Suzuki', 'Jimny', primary: true);
        $this->garageVehicle($user, 'Toyota', 'Hilux', primary: true);

        $this->actingAs($user);

        $this->assertSame($first->id, app(VehicleContext::class)->current()?->customerVehicleId);
    }

    public function test_the_picker_applies_a_make_and_model_without_a_generation(): void
    {
        $make = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Duster', 'slug' => 'duster']);

        Livewire::test(VehiclePicker::class)
            ->set('makeId', $make->id)
            ->set('modelId', $model->id)
            ->call('apply')
            ->assertHasNoErrors()
            ->assertDispatched('vehicle-changed');

        $this->assertSame('Dacia Duster', app(VehicleContext::class)->current()?->label());
    }

    public function test_the_picker_records_the_generation_when_one_is_chosen(): void
    {
        $make = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Hilux', 'slug' => 'hilux']);
        $generation = VehicleGeneration::create(['model_id' => $model->id, 'name' => 'AN120', 'year_from' => 2015]);

        Livewire::test(VehiclePicker::class)
            ->set('makeId', $make->id)
            ->set('modelId', $model->id)
            ->set('generationId', $generation->id)
            ->call('apply');

        $current = app(VehicleContext::class)->current();

        $this->assertSame($generation->id, $current?->generationId);
        $this->assertSame('Toyota Hilux AN120', $current->label());
    }

    /**
     * The selects cascade in the browser, but the ids arrive from the client and a mismatched
     * pair would otherwise store a model that does not belong to the chosen make.
     */
    public function test_the_picker_refuses_a_model_from_another_make(): void
    {
        $dacia = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);
        $toyota = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $hilux = VehicleModel::create(['make_id' => $toyota->id, 'name' => 'Hilux', 'slug' => 'hilux']);

        Livewire::test(VehiclePicker::class)
            ->set('makeId', $dacia->id)
            ->set('modelId', $hilux->id)
            ->call('apply')
            ->assertHasErrors('modelId');

        $this->assertNull(app(VehicleContext::class)->current());
    }

    public function test_changing_the_make_drops_the_stale_model_and_generation(): void
    {
        $make = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);
        $other = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Duster', 'slug' => 'duster']);

        Livewire::test(VehiclePicker::class)
            ->set('makeId', $make->id)
            ->set('modelId', $model->id)
            ->set('makeId', $other->id)
            ->assertSet('modelId', null)
            ->assertSet('generationId', null);
    }

    private function garageVehicle(User $user, string $makeName, string $modelName, bool $primary): CustomerVehicle
    {
        $make = VehicleMake::create(['name' => $makeName, 'slug' => strtolower($makeName)]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => $modelName, 'slug' => strtolower($modelName)]);

        return CustomerVehicle::create([
            'user_id' => $user->id,
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2020,
            'is_primary' => $primary,
        ]);
    }
}
