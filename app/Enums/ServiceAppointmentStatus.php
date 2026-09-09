<?php

namespace App\Enums;

/**
 * Where a fitting request has got to.
 *
 * These are requests, not bookings: the customer asks for a day and a rough time, and the
 * workshop answers. Nothing here promises the customer a slot, which is why there is no
 * "booked" state — claiming one without the workshop's calendar would be a promise the shop
 * cannot keep.
 */
enum ServiceAppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Trimisă',
            self::Confirmed => 'Confirmată',
            self::Completed => 'Finalizată',
            self::Declined => 'Refuzată',
            self::Cancelled => 'Anulată',
        };
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::Requested => 'pill-info',
            self::Confirmed => 'pill-positive',
            self::Completed => 'pill-neutral',
            self::Declined, self::Cancelled => 'pill-danger',
        };
    }

    /**
     * The moves an operator may make from here. Declined and cancelled are terminal: reviving a
     * refused request would tell the customer a workshop agreed to something it did not.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Declined],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Completed, self::Declined, self::Cancelled => [],
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Confirmed], true);
    }
}
