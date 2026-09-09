<?php

namespace App\Livewire\Customer;

use App\Enums\ServiceAppointmentStatus;
use App\Models\ServiceAppointment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront')]
class Appointments extends Component
{
    use WithPagination;

    /** The customer may withdraw a request the workshop has not closed yet. */
    public function cancel(int $appointmentId): void
    {
        $appointment = ServiceAppointment::query()
            ->whereBelongsTo(Auth::user())
            ->findOrFail($appointmentId);

        abort_unless($appointment->status->isOpen(), 422);

        $appointment->update([
            'status' => ServiceAppointmentStatus::Cancelled,
            'responded_at' => now(),
        ]);
    }

    public function render()
    {
        return view('livewire.customer.appointments', [
            'appointments' => ServiceAppointment::query()
                ->whereBelongsTo(Auth::user())
                ->with(['shop', 'service', 'vehicle.make', 'vehicle.model', 'order'])
                ->latest('id')
                ->paginate(15),
        ]);
    }
}
