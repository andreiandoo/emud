<?php

namespace App\Enums;

/**
 * What a pricing rule applies to, most specific first. A brand rule beats a supplier rule,
 * which beats the nearest category, which beats the shop-wide default.
 */
enum PricingScope: string
{
    case Brand = 'brand';
    case Supplier = 'supplier';
    case Category = 'category';
    case Default = 'default';

    public function label(): string
    {
        return match ($this) {
            self::Brand => 'Brand',
            self::Supplier => 'Furnizor',
            self::Category => 'Categorie',
            self::Default => 'Implicit',
        };
    }
}
