<?php

namespace Tests\Feature;

use App\Livewire\Customer\Garage as GarageComponent;
use App\Livewire\Customer\Register;
use App\Models\CustomerVehicle;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\Garage;
use App\Storefront\VehicleContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerGarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_saved_vehicle_becomes_primary_on_its_own(): void
    {
        $user = User::factory()->create();

        $vehicle = app(Garage::class)->add($user, $this->attributes('Dacia', 'Duster'));

        $this->assertTrue($vehicle->is_primary);
    }

    public function test_promoting_a_vehicle_demotes_the_previous_primary(): void
    {
        $user = User::factory()->create();
        $garage = app(Garage::class);
        $first = $garage->add($user, $this->attributes('Dacia', 'Duster'));
        $second = $garage->add($user, $this->attributes('Toyota', 'Hilux'));

        $garage->makePrimary($second);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
    }

    /**
     * A partial unique index backs the invariant, so a second primary must be impossible even
     * when something writes around the service.
     */
    public function test_the_database_refuses_a_second_primary_vehicle(): void
    {
        $user = User::factory()->create();
        $garage = app(Garage::class);
        $garage->add($user, $this->attributes('Dacia', 'Duster'));
        $second = $garage->add($user, $this->attributes('Toyota', 'Hilux'));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('customer_vehicles')->where('id', $second->id)->update(['is_primary' => true]);
    }

    public function test_two_customers_may_each_have_a_primary_vehicle(): void
    {
        $garage = app(Garage::class);
        $garage->add(User::factory()->create(), $this->attributes('Dacia', 'Duster'));
        $garage->add(User::factory()->create(), $this->attributes('Toyota', 'Hilux'));

        $this->assertSame(2, CustomerVehicle::query()->where('is_primary', true)->count());
    }

    /**
     * Leaving a customer with vehicles but no primary would stop the storefront personalising
     * for someone who never asked it to.
     */
    public function test_removing_the_primary_promotes_the_next_vehicle(): void
    {
        $user = User::factory()->create();
        $garage = app(Garage::class);
        $primary = $garage->add($user, $this->attributes('Dacia', 'Duster'));
        $other = $garage->add($user, $this->attributes('Toyota', 'Hilux'));

        $garage->remove($primary);

        $this->assertTrue($other->refresh()->is_primary);
    }

    public function test_saving_a_vehicle_makes_the_storefront_personalise_for_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(Garage::class)->add($user, $this->attributes('Jeep', 'Wrangler'));

        $this->assertSame('Jeep Wrangler', app(VehicleContext::class)->current()?->label());
    }

    public function test_a_customer_cannot_touch_a_vehicle_from_another_garage(): void
    {
        $owner = User::factory()->create();
        $vehicle = app(Garage::class)->add($owner, $this->attributes('Dacia', 'Duster'));

        $this->actingAs(User::factory()->create());

        try {
            Livewire::test(GarageComponent::class)->call('remove', $vehicle->id);
            $this->fail('Acting on another customer\'s vehicle should not be possible.');
        } catch (ModelNotFoundException) {
            // The lookup is scoped to the signed-in customer, so the row is simply not found.
        }

        $this->assertDatabaseHas('customer_vehicles', ['id' => $vehicle->id]);
    }

    public function test_the_garage_form_refuses_a_model_from_another_make(): void
    {
        $dacia = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);
        $toyota = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $hilux = VehicleModel::create(['make_id' => $toyota->id, 'name' => 'Hilux', 'slug' => 'hilux']);

        $this->actingAs(User::factory()->create());

        Livewire::test(GarageComponent::class)
            ->set('makeId', $dacia->id)
            ->set('modelId', $hilux->id)
            ->set('year', 2020)
            ->call('save')
            ->assertHasErrors('modelId');

        $this->assertDatabaseCount('customer_vehicles', 0);
    }

    public function test_the_garage_requires_authentication(): void
    {
        $this->get(route('customer.garage'))->assertRedirect(route('customer.login'));
    }

    /**
     * The admin area has its own sign-in screen; sending an operator to the shop form would
     * authenticate them into a page the admin middleware then refuses.
     */
    public function test_the_admin_area_still_redirects_to_the_admin_sign_in(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_registration_never_mints_an_administrator(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Andrei')
            ->set('email', 'client@example.com')
            ->set('password', 'parola-foarte-sigura-1')
            ->set('password_confirmation', 'parola-foarte-sigura-1')
            ->call('register');

        $user = User::query()->where('email', 'client@example.com')->sole();

        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertNull($user->marketing_consent_at);
    }

    public function test_marketing_consent_is_timestamped_only_when_given(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Andrei')
            ->set('email', 'consent@example.com')
            ->set('password', 'parola-foarte-sigura-1')
            ->set('password_confirmation', 'parola-foarte-sigura-1')
            ->set('marketing_consent', true)
            ->call('register');

        $this->assertNotNull(User::query()->where('email', 'consent@example.com')->sole()->marketing_consent_at);
    }

    /** @return array<string, mixed> */
    private function attributes(string $makeName, string $modelName): array
    {
        $make = VehicleMake::create(['name' => $makeName, 'slug' => strtolower($makeName)]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => $modelName, 'slug' => strtolower($modelName)]);

        return ['make_id' => $make->id, 'model_id' => $model->id, 'year' => 2020];
    }
}
