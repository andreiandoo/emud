<?php

namespace App\Enums;

/**
 * Integration levels a supplier may expose. The application must never assume
 * every supplier reaches the same level, so each capability is stored on its own
 * tri-state column: true (documented), false (documented as unavailable),
 * null (not established yet).
 */
enum SupplierCapability: string
{
    case Catalog = 'supports_catalog';
    case Prices = 'supports_prices';
    case Stock = 'supports_stock';
    case RealtimeStock = 'supports_realtime_stock';
    case OrderApi = 'supports_order_api';
    case TrackingApi = 'supports_tracking_api';
    case ReturnsApi = 'supports_returns_api';
    case TecDoc = 'supports_tecdoc';
    case AcesPies = 'supports_aces_pies';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Catalog',
            self::Prices => 'Prețuri',
            self::Stock => 'Stoc',
            self::RealtimeStock => 'Stoc realtime',
            self::OrderApi => 'API comenzi',
            self::TrackingApi => 'API tracking',
            self::ReturnsApi => 'API retururi',
            self::TecDoc => 'TecDoc',
            self::AcesPies => 'ACES/PIES',
        };
    }
}
