<?php

namespace App\Enums;

enum PricingMode: string
{
    /** The price follows the landed cost of the offer the shop would fulfil from. */
    case Auto = 'auto';

    /** An operator owns the price. Automatic repricing never touches it. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automat',
            self::Manual => 'Manual',
        };
    }
}
