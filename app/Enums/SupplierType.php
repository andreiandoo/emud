<?php

namespace App\Enums;

enum SupplierType: string
{
    case Distributor = 'distributor';
    case Manufacturer = 'manufacturer';
    case Marketplace = 'marketplace';
    case Aggregator = 'aggregator';
    case Wholesaler = 'wholesaler';

    public function label(): string
    {
        return match ($this) {
            self::Distributor => 'Distribuitor',
            self::Manufacturer => 'Producător',
            self::Marketplace => 'Marketplace',
            self::Aggregator => 'Agregator',
            self::Wholesaler => 'Angrosist',
        };
    }
}
