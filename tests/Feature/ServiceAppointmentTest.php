<?php

namespace Tests\Feature;

use App\Enums\ServiceAppointmentStatus;
use App\Enums\ServiceLeadEventType;
use App\Livewire\Admin\ServiceAppointmentsIndex;
use App\Livewire\Customer\Appointments as CustomerAppointments;
use App\Livewire\Storefront\ServiceShopPage;
use App\Models\CustomerVehicle;
use App\Models\Order;
use App\Models\ServiceAppointment;
use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_ask_for_a_fitting(): void
    {
        $shop = $this->shop();

        Livewire::test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion Popescu')
            ->set('phone', '0722000111')
            ->set('vehicleLabel', 'Suzuki Jimny 2018')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        $appointment = ServiceAppointment::query()->sole();

        $this->assertSame('Ion Popescu', $appointment->customer_name);
        $this->assertSame(ServiceAppointmentStatus::Requested, $appointment->status);
        $this->assertSame($shop->id, $appointment->service_shop_id);
    }

    /**
     * Submitting hands a name and a phone number to a third party, so it has to be an act rather
     * than a default.
     */
    public function test_the_request_is_refused_without_consent(): void
    {
        $shop = $this->shop();

        Livewire::test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion Popescu')
            ->set('phone', '0722000111')
            ->set('consent', false)
            ->call('submit')
            ->assertHasErrors('consent');

        $this->assertSame(0, ServiceAppointment::query()->count());
    }

    public function test_a_request_records_the_lead_it_produced(): void
    {
        $shop = $this->shop();

        Livewire::test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion')
            ->set('phone', '0722000111')
            ->set('consent', true)
            ->call('submit');

        $event = ServiceShopLeadEvent::query()->sole();

        $this->assertSame(ServiceLeadEventType::Appointment, $event->type);
        $this->assertSame($shop->id, $event->service_shop_id);
    }

    public function test_a_shop_that_does_not_take_requests_refuses_them(): void
    {
        $shop = $this->shop();
        $shop->update(['accepts_appointments' => false]);

        Livewire::test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion')
            ->set('phone', '0722000111')
            ->set('consent', true)
            ->call('submit')
            ->assertSet('formError', 'Acest service nu preia cereri de programare.');

        $this->assertSame(0, ServiceAppointment::query()->count());
    }

    /**
     * The order arrives as its checkout token — the same secret the confirmation page is reached
     * by — so a customer can attach an order they can already see and nobody can attach one they
     * cannot.
     */
    public function test_an_order_can_be_attached_by_its_checkout_token(): void
    {
        $shop = $this->shop();
        $order = Order::create([
            'number' => 'EM-1',
            'checkout_token' => (string) Str::uuid(),
            'customer_email' => 'ion@example.com',
        ]);

        Livewire::withQueryParams(['order' => $order->checkout_token])
            ->test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion')
            ->set('phone', '0722000111')
            ->set('consent', true)
            ->call('submit');

        $this->assertSame($order->id, ServiceAppointment::query()->sole()->order_id);
    }

    public function test_an_unknown_order_token_is_simply_ignored(): void
    {
        $shop = $this->shop();

        Livewire::withQueryParams(['order' => (string) Str::uuid()])
            ->test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('name', 'Ion')
            ->set('phone', '0722000111')
            ->set('consent', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertNull(ServiceAppointment::query()->sole()->order_id);
    }

    /**
     * The vehicle id arrives from the browser, and another customer's car carries their plate
     * number and service history.
     */
    public function test_a_car_from_another_garage_is_not_attached(): void
    {
        $shop = $this->shop();
        $stranger = User::factory()->create();
        $vehicle = $this->garageVehicle($stranger);

        Livewire::actingAs(User::factory()->create())
            ->test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->set('customerVehicleId', $vehicle->id)
            ->set('name', 'Ion')
            ->set('phone', '0722000111')
            ->set('consent', true)
            ->call('submit');

        $this->assertNull(ServiceAppointment::query()->sole()->customer_vehicle_id);
    }

    // ----------------------------------------------------------------- admin

    public function test_an_operator_moves_a_request_through_its_states(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $appointment = $this->appointment();

        Livewire::test(ServiceAppointmentsIndex::class)
            ->call('advance', $appointment->id, ServiceAppointmentStatus::Confirmed->value)
            ->assertSet('error', '');

        $this->assertSame(ServiceAppointmentStatus::Confirmed, $appointment->refresh()->status);
        $this->assertNotNull($appointment->responded_at);
    }

    /**
     * Reviving a refused request would tell the customer a workshop agreed to something it did
     * not, so declined and cancelled are terminal.
     */
    public function test_a_declined_request_cannot_be_revived(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $appointment = $this->appointment();
        $appointment->update(['status' => ServiceAppointmentStatus::Declined]);

        $component = Livewire::test(ServiceAppointmentsIndex::class)
            ->call('advance', $appointment->id, ServiceAppointmentStatus::Confirmed->value);

        $this->assertNotSame('', $component->get('error'));
        $this->assertSame(ServiceAppointmentStatus::Declined, $appointment->refresh()->status);
    }

    // -------------------------------------------------------------- customer

    public function test_a_customer_sees_and_can_withdraw_their_own_request(): void
    {
        $user = User::factory()->create();
        $appointment = $this->appointment($user);

        Livewire::actingAs($user)
            ->test(CustomerAppointments::class)
            ->assertSee('Atelierul Test')
            ->call('cancel', $appointment->id);

        $this->assertSame(ServiceAppointmentStatus::Cancelled, $appointment->refresh()->status);
    }

    public function test_a_customer_cannot_withdraw_someone_elses_request(): void
    {
        $appointment = $this->appointment(User::factory()->create());

        try {
            Livewire::actingAs(User::factory()->create())
                ->test(CustomerAppointments::class)
                ->call('cancel', $appointment->id);
            $this->fail('Another customer\'s request must not be reachable.');
        } catch (ModelNotFoundException) {
            // scoped to the signed-in customer, so it is simply not found
        }

        $this->assertSame(ServiceAppointmentStatus::Requested, $appointment->refresh()->status);
    }

    public function test_the_confirmation_page_is_reached_by_token(): void
    {
        $appointment = $this->appointment();

        $this->get(route('storefront.appointment', $appointment->token))
            ->assertOk()
            ->assertSee('Atelierul Test')
            ->assertSee('Cererea a fost trimisă');
    }

    private function shop(): ServiceShop
    {
        return ServiceShop::create([
            'name' => 'Atelierul Test',
            'slug' => 'atelierul-test',
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'status' => 'published',
            'accepts_appointments' => true,
        ]);
    }

    private function appointment(?User $user = null): ServiceAppointment
    {
        return $this->shop()->appointments()->create([
            'token' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'customer_name' => 'Ion Popescu',
            'customer_phone' => '0722000111',
            'status' => ServiceAppointmentStatus::Requested,
        ]);
    }

    private function garageVehicle(User $user): CustomerVehicle
    {
        $make = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Jimny', 'slug' => 'jimny']);

        return CustomerVehicle::create([
            'user_id' => $user->id,
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2018,
            'is_primary' => true,
        ]);
    }
}
