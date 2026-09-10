<?php

namespace App\Storefront;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\SupplierOffer;

/**
 * What the product page is allowed to say about stock.
 *
 * Stock lives on supplier offers, not on the product, and a dropshipped part often has several
 * suppliers carrying it. The best of them is what the customer can actually get, so that is what
 * is reported — reporting the worst would hide availability the shop really has.
 *
 * Nothing here promises a delivery date. The offer carries a dispatch window from the supplier
 * and that is stated as a window, because that is all it is.
 */
final class Availability
{
    /** Best first. A part in stock somewhere is in stock. */
    private const PRIORITY = [
        StockStatus::InStock->value => 0,
        StockStatus::LowStock->value => 1,
        StockStatus::Backorder->value => 2,
        StockStatus::Unknown->value => 3,
        StockStatus::OutOfStock->value => 4,
        StockStatus::Discontinued->value => 5,
    ];

    public function __construct(
        public readonly StockStatus $status,
        public readonly ?int $quantity,
        public readonly ?int $dispatchDaysMin,
        public readonly ?int $dispatchDaysMax,
    ) {}

    public static function forProduct(Product $product): self
    {
        $offers = SupplierOffer::query()
            ->where('is_active', true)
            ->whereHas('supplierProduct', fn ($query) => $query->where('product_id', $product->id))
            ->get(['stock_status', 'stock_quantity', 'dispatch_days_min', 'dispatch_days_max']);

        if ($offers->isEmpty()) {
            return new self(StockStatus::Unknown, null, null, null);
        }

        // ->value, not a string cast: the column is cast to an enum, and casting a backed enum
        // to string is a fatal error rather than the value.
        $best = $offers->sortBy(fn ($offer): int => self::PRIORITY[$offer->stock_status?->value ?? ''] ?? 9)->first();

        return new self(
            $best->stock_status ?? StockStatus::Unknown,
            $best->stock_quantity === null ? null : (int) $best->stock_quantity,
            $best->dispatch_days_min === null ? null : (int) $best->dispatch_days_min,
            $best->dispatch_days_max === null ? null : (int) $best->dispatch_days_max,
        );
    }

    public function label(): string
    {
        return match ($this->status) {
            StockStatus::InStock => 'În stoc',
            StockStatus::LowStock => 'Stoc limitat',
            StockStatus::Backorder => 'La comandă',
            StockStatus::OutOfStock => 'Stoc epuizat',
            StockStatus::Discontinued => 'Produs retras',
            StockStatus::Unknown => 'Disponibilitate la cerere',
        };
    }

    public function tone(): string
    {
        return match ($this->status) {
            StockStatus::InStock => 'positive',
            StockStatus::LowStock, StockStatus::Backorder => 'warning',
            StockStatus::OutOfStock, StockStatus::Discontinued => 'danger',
            StockStatus::Unknown => 'neutral',
        };
    }

    public function orderable(): bool
    {
        return ! in_array($this->status, [StockStatus::OutOfStock, StockStatus::Discontinued], true);
    }

    /** "Se expediază în 2–4 zile lucrătoare", when the supplier said so. */
    public function dispatchWindow(): ?string
    {
        $min = $this->dispatchDaysMin;
        $max = $this->dispatchDaysMax;

        if ($min === null && $max === null) {
            return null;
        }

        $days = match (true) {
            $min !== null && $max !== null && $min !== $max => $min.'–'.$max,
            default => (string) ($min ?? $max),
        };

        return 'Se expediază în '.$days.' zile lucrătoare';
    }
}
