<?php

namespace App\Enums;

/**
 * The part of the day a customer would prefer.
 *
 * Deliberately coarse. An exact time would read as a booking, and nothing here checks whether
 * the workshop is free then — a customer told "10:30" who arrives to a full bay blames the
 * shop that sent them, not the workshop.
 */
enum AppointmentSlot: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Anytime = 'anytime';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Dimineața (08:00–12:00)',
            self::Afternoon => 'După-amiaza (12:00–18:00)',
            self::Anytime => 'Oricând',
        };
    }
}
