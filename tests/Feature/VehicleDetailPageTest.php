<?php

namespace Tests\Feature;

use App\Enums\ServiceReminderType;
use App\Livewire\Customer\VehicleDetail;
use App\Models\CatalogSource;
use App\Models\CustomerVehicle;
use App\Models\ServiceShop;
use App\Models\User;
use App\Models\VehicleConfiguration;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleServiceReminder;
use App\Storefront\AddressBook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** The kilometres left are the target less the last reading, and turn into kilometres over once passed. */
    public function test_an_oil_change_by_kilometres_shows_how_far_off_it_is(): void
    {
        $this->vehicle->update(['mileage_km' => 118000]);
        VehicleServiceReminder::create([
            'customer_vehicle_id' => $this->vehicle->id,
            'type' => ServiceReminderType::OilAndFilter->value,
            'due_at_km' => 120000,
            'interval_km' => 15000,
            'is_active' => true,
        ]);

        $this->get(route('customer.garage.vehicle', $this->vehicle->slug))->assertOk()->assertSee('mai ai 2.000 km');

        $this->vehicle->update(['mileage_km' => 121500]);

        $this->get(route('customer.garage.vehicle', $this->vehicle->slug))->assertOk()->assertSee('depășit cu 1.500 km');
    }

    public function test_all_the_workshops_nearby_are_one_click_away(): void
    {
        app(AddressBook::class)->saveShipping($this->user, [
            'first_name' => 'Andrei',
            'last_name' => 'Popescu',
            'line_1' => 'Str. Exemplu 1',
            'city' => 'Ploiești',
            'county' => 'Prahova',
        ]);

        foreach (range(1, 5) as $number) {
            ServiceShop::create(['name' => 'Service '.$number, 'slug' => 'service-'.$number, 'county' => 'Prahova', 'city' => 'Ploiești', 'status' => 'published']);
        }

        $this->get(route('customer.garage.vehicle', $this->vehicle->slug))
            ->assertOk()
            ->assertSee('3 din 5')
            ->assertSee('Vezi toate cele 5')
            ->assertSee(route('storefront.services', ['county' => 'Prahova']), false);
    }

    public function test_the_engine_can_be_picked_from_the_catalogue(): void
    {
        $generation = VehicleGeneration::create(['model_id' => $this->vehicle->model_id, 'name' => 'L550', 'year_from' => 2014, 'year_to' => 2019]);
        $engine = VehicleEngine::create(['generation_id' => $generation->id, 'name' => '2.0 TD4', 'power_kw' => 110]);
        $configuration = VehicleConfiguration::create(['generation_id' => $generation->id, 'engine_id' => $engine->id, 'year' => 2016]);

        Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
            ->assertSee('2.0 TD4 · 150 CP · 2016')
            ->set('configurationPick', $configuration->id)
            ->call('saveConfiguration')
            ->assertHasNoErrors();

        $this->vehicle->refresh();

        $this->assertSame($configuration->id, $this->vehicle->configuration_id);
        $this->assertSame($generation->id, $this->vehicle->generation_id);
    }

    /** The id comes from the page, so a build of another model must not be linkable. */
    public function test_an_engine_of_another_model_is_refused(): void
    {
        $defender = VehicleModel::create(['make_id' => $this->vehicle->make_id, 'name' => 'Defender', 'slug' => 'defender']);
        $generation = VehicleGeneration::create(['model_id' => $defender->id, 'name' => 'L663', 'year_from' => 2020]);
        $configuration = VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2021]);

        try {
            Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
                ->set('configurationPick', $configuration->id)
                ->call('saveConfiguration');
            $this->fail('A build of another model must not be linked to this car.');
        } catch (ModelNotFoundException) {
            // scoped to the car's own model, so it is simply not found
        }

        $this->assertNull($this->vehicle->refresh()->configuration_id);
    }

    public function test_the_engine_is_read_off_the_saved_vin(): void
    {
        $generation = VehicleGeneration::create(['model_id' => $this->vehicle->model_id, 'name' => 'L550', 'year_from' => 2014, 'year_to' => 2019]);
        $configuration = VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2016]);
        $this->vehicle->update(['vin' => 'SALCA2BN2HH648377']);
        $this->connectVpic();

        Livewire::test(VehicleDetail::class, ['slug' => $this->vehicle->slug])
            ->assertSee('Caută motorizarea după serie')
            ->call('identifyFromVin');

        $this->assertSame($configuration->id, $this->vehicle->refresh()->configuration_id);
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

    /** vPIC as it answers for a Discovery Sport built for Europe: the make and the model year only. */
    private function connectVpic(): void
    {
        CatalogSource::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'NHTSA vPIC',
            'code' => 'VPIC',
            'source_type' => 'government_vehicle_database',
            'rights_class' => 'public_information',
            'allow_internal' => true,
            'allow_ecommerce' => true,
            'allow_derived' => true,
            'allow_api_redistribution' => true,
            'is_active' => true,
        ]);

        Http::fake(['vpic.nhtsa.dot.gov/*' => Http::response(['Results' => [[
            'Make' => 'LAND ROVER',
            'Model' => '',
            'ModelYear' => '2017',
            'VIN' => 'SALCA2BN2HH648377',
        ]]])]);
    }
}
