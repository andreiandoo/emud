<?php

namespace App\Livewire\Customer;

use App\Directory\NearbyShops;
use App\Enums\ServiceReminderType;
use App\Models\CustomerVehicle;
use App\Models\OrderItem;
use App\Models\VehicleServiceReminder;
use App\Storefront\ServicePlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class VehicleDetail extends Component
{
    use WithFileUploads;

    public CustomerVehicle $vehicle;

    public ?int $mileage_km = null;

    public string $reminderType = '';

    public ?string $reminderDueOn = null;

    public ?int $reminderDueAtKm = null;

    public string $reminderNotes = '';

    /** A photograph of the car, saved as soon as it is chosen. */
    public mixed $photo = null;

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
            ->with(['make', 'model', 'generation', 'configuration.engine', 'collection'])
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

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'max:8192']], [
            'photo.image' => 'Alege o fotografie (JPG, PNG sau WebP).',
            'photo.max' => 'Fotografia poate avea cel mult 8 MB.',
        ]);

        $previous = $this->vehicle->photo_path;

        $this->vehicle->update(['photo_path' => $this->photo->store('garage', 'public')]);

        // The old file goes with it: a garage photographed three times keeps one picture, not
        // three, on a disk served to the web.
        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        $this->photo = null;
        $this->status = 'Fotografia a fost salvată.';
    }

    public function removePhoto(): void
    {
        if ($this->vehicle->photo_path !== null) {
            Storage::disk('public')->delete($this->vehicle->photo_path);
            $this->vehicle->update(['photo_path' => null]);
        }

        $this->status = 'Fotografia a fost ștearsă.';
    }

    /**
     * Fills the deadline form for a kind of deadline — with what is already set for it, so the
     * same button adds a deadline and changes one — or empty for "any other deadline".
     */
    public function startReminder(string $type = ''): void
    {
        $this->resetValidation();

        $existing = $type === '' ? null : $this->vehicle->reminders()->where('type', $type)->first();

        $this->reminderType = $type;
        $this->reminderDueOn = $existing?->due_on?->toDateString();
        $this->reminderDueAtKm = $existing?->due_at_km;
        $this->reminderNotes = (string) ($existing?->notes ?? '');
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
            'reminderDueOn.date' => 'Scrie data ca zz/ll/aaaa.',
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
        $this->dispatch('reminder-saved');
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

    public function render(ServicePlan $plan, NearbyShops $nearby)
    {
        $reminders = $plan->forVehicle($this->vehicle);

        return view('livewire.customer.vehicle-detail', [
            'reminders' => $reminders,
            'byType' => $reminders->keyBy(fn (VehicleServiceReminder $reminder): string => $reminder->type->value),
            'essentials' => ServiceReminderType::essentials(),
            'types' => ServiceReminderType::cases(),
            'history' => $this->partsHistory(),
            'location' => $nearby->locationOf(auth()->user()),
            'nearby' => $nearby->forUser(auth()->user(), $this->vehicle, 3),
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
