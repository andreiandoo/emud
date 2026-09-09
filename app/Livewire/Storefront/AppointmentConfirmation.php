<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceAppointment;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * What the customer sees after asking for a fitting.
 *
 * Reached by the request's own token so a visitor with no account can come back to it, and so
 * knowing an id is never enough to read someone else's name and phone number.
 */
#[Layout('layouts::storefront')]
class AppointmentConfirmation extends Component
{
    public ServiceAppointment $appointment;

    public function mount(string $token): void
    {
        $this->appointment = ServiceAppointment::query()
            ->with(['shop', 'service', 'vehicle.make', 'vehicle.model', 'order'])
            ->where('token', $token)
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.appointment-confirmation');
    }
}
