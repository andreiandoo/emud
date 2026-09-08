<?php

namespace Tests\Feature;

use App\Enums\ServiceReminderType;
use App\Livewire\Customer\VehicleDetail;
use App\Models\CustomerVehicle;
use App\Models\Order;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\VehicleServiceReminder;
use App\Storefront\ServicePlan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerVehicleDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerVehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->vehicle = $this->garageVehicle($this->user);
        $this->actingAs($this->user);
    }

    public function test_a_customer_can_open_their_own_vehicle(): void
    {
        $this->get(route('customer.garage.vehicle', $this->vehicle->id))
            ->assertOk()
            ->assertSee('Toyota Hilux');
    }

    /**
     * The page shows a plate number and a service history, so an id from someone else's garage
     * must not resolve.
     */
    public function test_another_customers_vehicle_is_not_reachable(): void
    {
        $stranger = $this->garageVehicle(User::factory()->create());

        $this->get(route('customer.garage.vehicle', $stranger->id))->assertNotFound();
    }

    public function test_mileage_is_recorded_with_the_date_it_was_read(): void
    {
        Livewire::test(VehicleDetail::class, ['vehicleId' => $this->vehicle->id])
            ->set('mileage_km', 128000)
            ->call('saveMileage')
            ->assertHasNoErrors();

        $this->vehicle->refresh();

        $this->assertSame(128000, $this->vehicle->mileage_km);
        $this->assertSame(now()->toDateString(), $this->vehicle->mileage_recorded_on?->toDateString());
    }

    public function test_a_reminder_can_be_added(): void
    {
        Livewire::test(VehicleDetail::class, ['vehicleId' => $this->vehicle->id])
            ->set('reminderType', ServiceReminderType::Itp->value)
            ->set('reminderDueOn', now()->addMonths(3)->toDateString())
            ->call('addReminder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vehicle_service_reminders', [
            'customer_vehicle_id' => $this->vehicle->id,
            'type' => 'itp',
        ]);
    }

    /**
     * Two "next ITP" rows would leave the customer guessing which date is real, so re-adding a
     * kind changes the existing one.
     */
    public function test_adding_the_same_kind_twice_updates_rather_than_duplicates(): void
    {
        $component = Livewire::test(VehicleDetail::class, ['vehicleId' => $this->vehicle->id]);

        $component->set('reminderType', ServiceReminderType::Itp->value)
            ->set('reminderDueOn', now()->addMonth()->toDateString())
            ->call('addReminder');

        $component->set('reminderType', ServiceReminderType::Itp->value)
            ->set('reminderDueOn', now()->addYear()->toDateString())
            ->call('addReminder');

        $this->assertSame(1, VehicleServiceReminder::query()->count());
        $this->assertSame(
            now()->addYear()->toDateString(),
            VehicleServiceReminder::query()->sole()->due_on->toDateString()
        );
    }

    public function test_an_overdue_reminder_reports_itself_as_overdue(): void
    {
        $reminder = $this->reminder(ServiceReminderType::Itp, dueOn: now()->subDays(10));

        $this->assertSame('overdue', $reminder->status());
        $this->assertLessThan(0, $reminder->daysRemaining());
    }

    public function test_a_reminder_due_within_a_month_is_flagged(): void
    {
        $this->assertSame('due_soon', $this->reminder(ServiceReminderType::Rca, dueOn: now()->addDays(10))->status());
    }

    public function test_a_reminder_with_no_dates_is_unscheduled_rather_than_overdue(): void
    {
        $this->assertSame('unscheduled', $this->reminder(ServiceReminderType::Brakes)->status());
    }

    /**
     * Owners act on whichever limit arrives first, so a mileage target that is nearly reached
     * must outrank a date that is months away.
     */
    public function test_mileage_can_make_an_item_more_urgent_than_its_date(): void
    {
        $this->vehicle->update(['mileage_km' => 99500]);
        $reminder = $this->reminder(ServiceReminderType::OilAndFilter, dueOn: now()->addYear());
        $reminder->update(['due_at_km' => 100000]);

        $this->assertSame('due_soon', $reminder->refresh()->status());
        $this->assertSame(500, $reminder->kilometresRemaining());
    }

    public function test_mileage_remaining_is_unknown_without_an_odometer_reading(): void
    {
        $reminder = $this->reminder(ServiceReminderType::OilAndFilter);
        $reminder->update(['due_at_km' => 100000]);

        $this->assertNull($reminder->refresh()->kilometresRemaining());
    }

    /**
     * Scheduling the next service from the completion date rather than the old due date stops a
     * late service permanently shifting the whole plan earlier.
     */
    public function test_completing_an_item_schedules_the_next_one_from_today(): void
    {
        $this->vehicle->update(['mileage_km' => 50000]);
        $reminder = $this->reminder(ServiceReminderType::OilAndFilter, dueOn: now()->subMonths(2));

        app(ServicePlan::class)->markDone($reminder->fresh()->setRelation('vehicle', $this->vehicle->fresh()));

        $updated = $reminder->refresh();

        $this->assertSame(now()->toDateString(), $updated->last_done_on?->toDateString());
        $this->assertSame(now()->addMonths(12)->toDateString(), $updated->due_on?->toDateString());
        $this->assertSame(60000, $updated->due_at_km);
    }

    public function test_a_reminder_can_be_removed(): void
    {
        $reminder = $this->reminder(ServiceReminderType::Itp, dueOn: now()->addMonth());

        Livewire::test(VehicleDetail::class, ['vehicleId' => $this->vehicle->id])
            ->call('removeReminder', $reminder->id);

        $this->assertDatabaseCount('vehicle_service_reminders', 0);
    }

    public function test_a_reminder_from_another_vehicle_cannot_be_touched(): void
    {
        $stranger = $this->garageVehicle(User::factory()->create());
        $foreign = VehicleServiceReminder::create(['customer_vehicle_id' => $stranger->id, 'type' => 'itp']);

        try {
            Livewire::test(VehicleDetail::class, ['vehicleId' => $this->vehicle->id])
                ->call('removeReminder', $foreign->id);
            $this->fail('A reminder on another vehicle must not be reachable.');
        } catch (ModelNotFoundException) {
            // scoped to this vehicle, so it is simply not found
        }

        $this->assertDatabaseHas('vehicle_service_reminders', ['id' => $foreign->id]);
    }

    public function test_parts_bought_for_this_vehicle_are_listed(): void
    {
        $order = Order::create([
            'number' => 'EM-1',
            'user_id' => $this->user->id,
            'checkout_token' => (string) Str::uuid(),
            'customer_email' => $this->user->email,
            'currency' => 'RON',
            'grand_total' => 100,
            'placed_at' => now(),
        ]);
        $order->items()->create(['name' => 'Filtru ulei Hilux', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50, 'customer_vehicle_id' => $this->vehicle->id]);
        $order->items()->create(['name' => 'Piesa altei masini', 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50]);

        $this->get(route('customer.garage.vehicle', $this->vehicle->id))
            ->assertSee('Filtru ulei Hilux')
            ->assertDontSee('Piesa altei masini');
    }

    private function reminder(ServiceReminderType $type, mixed $dueOn = null): VehicleServiceReminder
    {
        $reminder = VehicleServiceReminder::create([
            'customer_vehicle_id' => $this->vehicle->id,
            'type' => $type->value,
            'due_on' => $dueOn?->toDateString(),
        ]);

        return $reminder->setRelation('vehicle', $this->vehicle);
    }

    private function garageVehicle(User $user): CustomerVehicle
    {
        $make = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota-'.Str::random(5)]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Hilux', 'slug' => 'hilux-'.Str::random(5)]);

        return CustomerVehicle::create([
            'user_id' => $user->id,
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2020,
            'is_primary' => true,
        ]);
    }
}
