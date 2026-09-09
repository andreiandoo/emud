<?php

namespace App\Enums;

/**
 * What a workshop actually received from the directory.
 *
 * These are the billable moments. A phone number revealed and a route opened are worth
 * something to a workshop even when no appointment follows, and a listing sold on position
 * alone cannot be argued about without them.
 */
enum ServiceLeadEventType: string
{
    case PhoneReveal = 'phone_reveal';
    case WebsiteClick = 'website_click';
    case Directions = 'directions';
    case Appointment = 'appointment';

    public function label(): string
    {
        return match ($this) {
            self::PhoneReveal => 'Telefon afișat',
            self::WebsiteClick => 'Click pe website',
            self::Directions => 'Rută pe hartă',
            self::Appointment => 'Cerere de programare',
        };
    }
}
