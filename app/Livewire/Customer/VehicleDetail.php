<?php

namespace App\Livewire\Customer;

use App\Enums\ServiceReminderType;
use App\Models\CustomerVehicle;
use App\Models\OrderItem;
use App\Models\VehicleServiceReminder;
use App\Storefront\ServicePlan;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class VehicleDetail extends Component
{
    public CustomerVehicle $vehicle;

    public ?int $mileage_km = null;

    public string $reminderType = '';

    public ?string $reminderDueOn = null;

    public ?int $reminderDueAtKm = null;

    public string $reminderNotes = '';

    public string $status = '';

    /**
     * Looked up by its readable address (dacia-duster-2018) inside the signed-in customer's own
     * garage, so the same address in someone else's garage is a 404 rather than a page of their
     * plate number and service history. The slug is only unique per customer, which is why the
     * scoping is part of the lookup rather than a check after it.
     *
     * The route parameter is deliberately not called "vehicle": matching the typed
     * CustomerVehicle property makes implicit binding resolve it before mount runs, which would
     * skip this scoping entirely.
     */
    public function mount(string $slug): void
    {
        $this->vehicle = CustomerVehicle::query()
            ->where('user_id', auth()->id())
            ->where('slug', $slug)
            ->with(['make', 'model', 'generation', 'configuration.engine'])
            ->firstOrFail();

        $this->mileage_km = $this->vehicle->mileage_km;
    }

    public function saveMileage(): void
    {
        $data = $this->validate([
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
        ]);

        // The date the reading was taken travels with it: a kilometre figure with no date
        // cannot say whether a mileage-based item is due.
        $this->vehicle->update([
            'mileage_km' => $data['mileage_km'],
            'mileage_recorded_on' => $data['mileage_km'] === null ? null : now()->toDateString(),
        ]);

        $this->status = 'Kilometrajul a fost actualizat.';
    }

    public function addReminder(): void
    {
        $data = $this->validate([
            'reminderType' => ['required', Rule::enum(ServiceReminderType::class)],
            'reminderDueOn' => ['nullable', 'date'],
            'reminderDueAtKm' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'reminderNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'reminderType.required' => 'Alege ce anume urmărești.',
        ]);

        $type = ServiceReminderType::from($data['reminderType']);

        // updateOrCreate rather than create: the table allows one reminder per kind per vehicle,
        // and a customer re-adding an existing kind means "change the date", not "fail".
        VehicleServiceReminder::query()->updateOrCreate(
            ['customer_vehicle_id' => $this->vehicle->id, 'type' => $type->value],
            [
                'due_on' => $data['reminderDueOn'],
                'due_at_km' => $data['reminderDueAtKm'],
                'notes' => $data['reminderNotes'] ?: null,
                'interval_months' => $type->defaultIntervalMonths(),
                'interval_km' => $type->defaultIntervalKm(),
                'is_active' => true,
            ],
        );

        $this->reset(['reminderType', 'reminderDueOn', 'reminderDueAtKm', 'reminderNotes']);
        $this->status = 'Scadența a fost salvată.';
    }

    public function markDone(int $reminderId, ServicePlan $plan): void
    {
        $plan->markDone($this->ownedReminder($reminderId));

        $this->status = 'Am notat lucrarea și am programat următoarea scadență.';
    }

    public function removeReminder(int $reminderId): void
    {
        $this->ownedReminder($reminderId)->delete();

        $this->status = 'Scadența a fost ștearsă.';
    }

    public function render(ServicePlan $plan)
    {
        return view('livewire.customer.vehicle-detail', [
            'reminders' => $plan->forVehicle($this->vehicle),
            'suggestions' => $plan->suggestedFor($this->vehicle),
            'types' => ServiceReminderType::cases(),
            'history' => $this->partsHistory(),
        ]);
    }

    /**
     * Order lines bought while this vehicle was the active one. The link is stamped at checkout;
     * orders placed before that existed simply do not appear, which is honest — inferring which
     * car an old order was for would be a guess presented as a record.
     *
     * @return Collection<int, OrderItem>
     */
    private function partsHistory(): Collection
    {
        return OrderItem::query()
            ->where('customer_vehicle_id', $this->vehicle->id)
            ->whereHas('order', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('order')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function ownedReminder(int $reminderId): VehicleServiceReminder
    {
        return VehicleServiceReminder::query()
            ->where('customer_vehicle_id', $this->vehicle->id)
            ->findOrFail($reminderId);
    }
}
