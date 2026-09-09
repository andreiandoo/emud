<?php

namespace App\Directory;

use App\Enums\ServiceAppointmentStatus;
use App\Enums\ServiceLeadEventType;
use App\Models\ServiceAppointment;
use App\Models\ServiceShop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creating and moving fitting requests.
 *
 * A request and its lead event are written together: the event is what a workshop is billed
 * for, so a request that exists without one would be work given away, and an event without a
 * request would be a charge with nothing behind it.
 */
class AppointmentService
{
    public function __construct(private LeadTracker $leads) {}

    /** @param array<string, mixed> $attributes */
    public function request(ServiceShop $shop, array $attributes): ServiceAppointment
    {
        // Refused rather than quietly accepted: a workshop that has switched appointments off
        // is not reading them, and a request it never sees is worse than an honest phone number.
        if (! $shop->accepts_appointments) {
            throw new RuntimeException('Acest service nu preia cereri de programare.');
        }

        return DB::transaction(function () use ($shop, $attributes): ServiceAppointment {
            $appointment = $shop->appointments()->create([
                ...$attributes,
                'token' => (string) Str::uuid(),
                'status' => ServiceAppointmentStatus::Requested,
            ]);

            $this->leads->record($shop, ServiceLeadEventType::Appointment, $appointment);

            return $appointment;
        });
    }

    public function transition(ServiceAppointment $appointment, ServiceAppointmentStatus $to, ?string $note = null): ServiceAppointment
    {
        if (! in_array($to, $appointment->status->allowedNext(), true)) {
            throw new RuntimeException(
                "Cererea nu poate trece din „{$appointment->status->label()}” în „{$to->label()}”.",
            );
        }

        $appointment->update([
            'status' => $to,
            'responded_at' => now(),
            'internal_note' => $note ?? $appointment->internal_note,
        ]);

        return $appointment->refresh();
    }
}
