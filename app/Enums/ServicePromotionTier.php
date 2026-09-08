<?php

namespace App\Enums;

/**
 * What a workshop has paid for. Ordering follows this, and every tier above None is labelled to
 * the reader, because ranking that money influenced has to be disclosed.
 */
enum ServicePromotionTier: string
{
    case None = 'none';
    case Listed = 'listed';
    case Featured = 'featured';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Listare gratuită',
            self::Listed => 'Listare plătită',
            self::Featured => 'Promovat',
            self::Premium => 'Partener premium',
        };
    }

    public function isPaid(): bool
    {
        return $this !== self::None;
    }

    /** Higher sorts first. */
    public function weight(): int
    {
        return match ($this) {
            self::Premium => 3,
            self::Featured => 2,
            self::Listed => 1,
            self::None => 0,
        };
    }
}
