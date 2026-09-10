<?php

namespace App\Enums;

enum PriceChangeStatus: string
{
    case Applied = 'applied';
    case Pending = 'pending';
    case Rejected = 'rejected';

    /** A newer decision for the same variant replaced this one before anyone acted on it. */
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Aplicat',
            self::Pending => 'De aprobat',
            self::Rejected => 'Respins',
            self::Superseded => 'Înlocuit',
        };
    }
}
