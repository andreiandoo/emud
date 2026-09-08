<?php

namespace App\Suppliers;

use App\Enums\StockStatus;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

/**
 * Retires supplier products that stopped appearing in the feed.
 *
 * Never deletes: a supplier that drops an article for a week and brings it back
 * must not lose its price history, its canonical mapping or its identifiers. The
 * product is marked discontinued and its offer is moved out of the sellable stock
 * states, which is enough to keep it off the storefront.
 */
class SupplierCatalogRetirement
{
    /** @return array{retired: int, threshold_days: int, cutoff: string} */
    public function retireMissing(Supplier $supplier): array
    {
        $days = (int) ($supplier->settings['discontinue_after_days'] ?? config('emud.suppliers.discontinue_after_days'));
        $cutoff = now()->subDays($days);
        $retired = 0;

        SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNull('discontinued_at')
            ->where(fn ($query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $cutoff))
            ->select('id')
            ->chunkById(500, function (Collection $products) use (&$retired): void {
                $ids = $products->pluck('id')->all();

                SupplierProduct::query()->whereIn('id', $ids)->update([
                    'discontinued_at' => now(),
                    'updated_at' => now(),
                ]);

                SupplierOffer::query()->whereIn('supplier_product_id', $ids)->update([
                    'stock_status' => StockStatus::Discontinued->value,
                    'stock_quantity' => 0,
                    'updated_at' => now(),
                ]);

                $retired += count($ids);
            });

        return ['retired' => $retired, 'threshold_days' => $days, 'cutoff' => $cutoff->toDateTimeString()];
    }
}
