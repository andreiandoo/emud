<?php

namespace App\Commerce;

use App\Models\ProductVariant;
use App\Models\SupplierOffer;

class BestSupplierOffer
{
    public function for(ProductVariant $variant): ?SupplierOffer
    {
        return SupplierOffer::query()
            ->select('supplier_offers.*')
            ->join('supplier_products', 'supplier_products.id', '=', 'supplier_offers.supplier_product_id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->where('supplier_products.variant_id', $variant->id)
            ->where('suppliers.is_active', true)
            ->whereIn('supplier_offers.stock_status', ['in_stock', 'low_stock', 'backorder'])
            ->where(fn ($query) => $query->whereNull('supplier_offers.stale_after')->orWhere('supplier_offers.stale_after', '>', now()))
            ->orderByRaw("CASE supplier_offers.stock_status WHEN 'in_stock' THEN 0 WHEN 'low_stock' THEN 1 ELSE 2 END")
            ->orderBy('suppliers.priority')
            ->orderBy('supplier_offers.cost_price')
            ->first();
    }
}
