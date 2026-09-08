<?php

namespace App\Enums;

/**
 * Why a supplier is worth integrating at all. A supplier that fits none of these
 * roles should not receive an adapter, regardless of catalogue size.
 */
enum SupplierStrategicRole: string
{
    /** Unique or vehicle-specific assortment we cannot source elsewhere. */
    case Specialist = 'specialist';

    /** Reliable feed, stock and dropship logistics for everyday fulfilment. */
    case Backbone = 'backbone';

    /** Broad availability used to avoid lost sales when specialists are out of stock. */
    case Fallback = 'fallback';

    /** Long-tail accessories that only widen the basket. */
    case Longtail = 'longtail';

    public function label(): string
    {
        return match ($this) {
            self::Specialist => 'Specialist diferențiat',
            self::Backbone => 'Coloană de fulfilment',
            self::Fallback => 'Rezervă preț/disponibilitate',
            self::Longtail => 'Long-tail accesorii',
        };
    }
}
