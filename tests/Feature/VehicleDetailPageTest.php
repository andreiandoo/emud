<?php

namespace Tests\Feature;

use App\Livewire\Customer\VehicleDetail;
use App\Models\CustomerVehicle;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleServiceReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VehicleDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerVehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $make = VehicleMake::create(['name' => 'Land Rover', 'slug' => 'land-rover']);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Discovery Sport', 'slug' => 'discovery-sport']);
        $this->vehicle = CustomerVehicle::create([
            'user_id' => $this->user->id,
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2016,
            'is_primary' => true,
        ]);
        $this->actingAs($this->user);
    }

    public function test_the_essential_deadlines_are_offered_on_every_car(): void
    {
        $this->get(route('customer.garage.vehicle', $this->vehicle->slug))
            ->assertOk()
            ->assertSee('ITP')
            ->assertSee('Verificare RAR')
            ->assertSee('Rovinietă')
            ->assertSee('Asigurare RCA')
            ->assertSee('Ulei și filtru de ulei')
            ->assertSee('Adaugă scadență');
    }

    public function test_the_deadline_form_opens_with_what_is_already_set(): void
    {
        VehicleServiceReminder::create([
            'customer_vehicle_id' => $this->vehicle->id,
            'type' => 'itp',
            'due_on' => '2027-03-24',
            'notes' => 'Stația din Ploiești',
        ]);

        Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
            ->call('startReminder', 'itp')
            ->assertSet('reminderType', 'itp')
            ->assertSet('reminderDueOn', '2027-03-24')
            ->assertSet('reminderNotes', 'Stația din Ploiești');
    }

    public function test_saving_a_deadline_closes_the_dialog_and_lists_it_day_first(): void
    {
        Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
            ->call('startReminder', 'rovinieta')
            ->set('reminderDueOn', '2027-01-15')
            ->call('addReminder')
            ->assertHasNoErrors()
            ->assertDispatched('reminder-saved')
            ->assertSee('15/01/2027');
    }

    public function test_a_photo_of_the_car_is_saved_as_soon_as_it_is_chosen(): void
    {
        Storage::fake('public');

        Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
            ->set('photo', UploadedFile::fake()->image('discovery.jpg', 800, 500))
            ->assertHasNoErrors();

        $path = $this->vehicle->refresh()->photo_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, (string) $this->vehicle->photoUrl());
    }

    public function test_replacing_the_photo_removes_the_old_file(): void
    {
        Storage::fake('public');
        $component = Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug]);

        $component->set('photo', UploadedFile::fake()->image('first.jpg'));
        $first = $this->vehicle->refresh()->photo_path;
        $component->set('photo', UploadedFile::fake()->image('second.jpg'));

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($this->vehicle->refresh()->photo_path);
    }
}
