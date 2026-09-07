<?php

namespace App\Storefront\Compatibility;

/**
 * How confidently a product is known to fit the customer's vehicle.
 *
 * The distinction that matters commercially is between Confirmed and the two uncertain states:
 * a customer who buys a part labelled "fits" and finds it does not is a return, a refund and a
 * lost customer. Unknown must never be presented as compatible.
 */
enum CompatibilityVerdict: string
{
    /** A fitment record covers this exact vehicle. */
    case Confirmed = 'confirmed';

    /** It fits, but only with modification or under a stated condition. */
    case Conditional = 'conditional';

    /** Fitments exist for this model but depend on detail the customer has not given. */
    case RequiresVehicleDetail = 'requires_vehicle_detail';

    /** Fitment data covers this model and excludes this vehicle. */
    case Incompatible = 'incompatible';

    /** No fitment data at all; nothing can be claimed either way. */
    case Unknown = 'unknown';

    public function fits(): bool
    {
        return $this !== self::Incompatible;
    }

    public function isCertain(): bool
    {
        return $this === self::Confirmed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Se potrivește',
            self::Conditional => 'Se potrivește cu modificări',
            self::RequiresVehicleDetail => 'Verifică detaliile mașinii',
            self::Incompatible => 'Nu se potrivește',
            self::Unknown => 'Compatibilitate necunoscută',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Confirmed => 'Există date de compatibilitate care acoperă exact mașina ta.',
            self::Conditional => 'Montajul cere modificări sau condiții suplimentare. Citește notele produsului.',
            self::RequiresVehicleDetail => 'Compatibilitatea depinde de generație sau motorizare. Completează-le în garaj pentru un răspuns exact.',
            self::Incompatible => 'Datele de compatibilitate exclud această mașină.',
            self::Unknown => 'Nu avem încă date de compatibilitate pentru acest produs. Verifică înainte de comandă.',
        };
    }
}
