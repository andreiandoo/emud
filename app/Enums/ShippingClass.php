<?php

namespace App\Enums;

/**
 * Freight shape of an article.
 *
 * A 4x4 catalogue mixes soft shackles with steel bumpers and sets of mud tyres,
 * so a single shipping cost across the assortment turns high-margin products into
 * losses. Free shipping must be decided per class, not per basket total alone.
 */
enum ShippingClass: string
{
    case SmallParcel = 'small_parcel';
    case MediumParcel = 'medium_parcel';
    case HeavyParcel = 'heavy_parcel';
    case Oversize = 'oversize';
    case Tyre = 'tyre';
    case Wheel = 'wheel';
    case Pallet = 'pallet';
    case Ltl = 'ltl';
    case Hazardous = 'hazardous';

    public function label(): string
    {
        return match ($this) {
            self::SmallParcel => 'Colet mic',
            self::MediumParcel => 'Colet mediu',
            self::HeavyParcel => 'Colet greu',
            self::Oversize => 'Supradimensionat',
            self::Tyre => 'Anvelopă',
            self::Wheel => 'Jantă',
            self::Pallet => 'Palet',
            self::Ltl => 'Grupaj (LTL)',
            self::Hazardous => 'Periculos (ADR)',
        };
    }

    /** Classes whose reverse logistics are expensive enough to change the decision to stock them. */
    public function hasPunishingReturns(): bool
    {
        return in_array($this, [self::Oversize, self::Pallet, self::Ltl, self::Hazardous, self::Tyre], true);
    }
}
