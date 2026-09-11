<?php

namespace App\Storefront;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

/**
 * What the shop is allowed to say about stock and delivery.
 *
 * Stock lives on supplier offers, not on the product, and a dropshipped part often has several
 * suppliers carrying it. The best of them is what the customer can actually get, so that is what
 * is reported — reporting the worst would hide availability the shop really has. Each country the
 * part ships from is listed as well: a warehouse in Poland is a different wait than one here.
 *
 * Delivery is the supplier's dispatch window plus the courier's usual transit from that country
 * (emud.delivery.transit_days): an estimate, stated as a range, never a date.
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

    /** What a listing eager-loads so each card can say when the part ships without a query of its own. */
    public const LISTING_RELATIONS = [
        'supplierProducts:id,product_id,supplier_id,discontinued_at',
        'supplierProducts.offer.warehouse:id,country_code',
        'supplierProducts.supplier:id,is_active,country_code',
    ];

    /**
     * @param  list<array{country: ?string, status: StockStatus, delivery: array{0: int, 1: int}|null}>  $sources  one per country, best first
     * @param  bool  $confirmed  false when the stock comes from a feed past its freshness window
     */
    public function __construct(
        public readonly StockStatus $status,
        public readonly ?int $quantity,
        public readonly ?int $dispatchDaysMin,
        public readonly ?int $dispatchDaysMax,
        public readonly array $sources = [],
        public readonly bool $confirmed = true,
    ) {}

    public static function forProduct(Product $product): self
    {
        // routable(), not just is_active: an offer from a paused supplier, or one past its
        // freshness window, cannot be sold, so it must not be what the page promises either.
        $offers = SupplierOffer::query()
            ->routable()
            ->with(['warehouse', 'supplierProduct.supplier'])
            ->whereHas('supplierProduct', fn ($query) => $query->where('product_id', $product->id))
            ->get();

        if ($offers->isNotEmpty()) {
            return self::fromOffers($offers, true);
        }

        // A feed that has not refreshed within its window still said something. It is shown as
        // stock at the supplier, to be confirmed with the order, rather than as nothing at all.
        $lastKnown = SupplierOffer::query()
            ->where('is_active', true)
            ->with(['warehouse', 'supplierProduct.supplier'])
            ->whereHas('supplierProduct', fn ($query) => $query
                ->where('product_id', $product->id)
                ->whereNull('discontinued_at')
                ->whereHas('supplier', fn ($supplier) => $supplier->where('is_active', true)))
            ->get();

        return self::fromOffers($lastKnown, false);
    }

    /**
     * The same answer from what a listing already loaded (LISTING_RELATIONS), or null when it did
     * not load it: a card must never cost a query of its own.
     */
    public static function forListing(Product $product): ?self
    {
        if (! $product->relationLoaded('supplierProducts')) {
            return null;
        }

        $offers = $product->supplierProducts
            ->filter(fn (SupplierProduct $row): bool => $row->discontinued_at === null
                && (bool) $row->supplier?->is_active
                && (bool) $row->offer?->is_active)
            ->map(fn (SupplierProduct $row): SupplierOffer => $row->offer->setRelation('supplierProduct', $row))
            ->values();

        $fresh = $offers->filter(fn (SupplierOffer $offer): bool => $offer->stale_after === null || $offer->stale_after->isFuture());

        return $fresh->isNotEmpty() ? self::fromOffers($fresh, true) : self::fromOffers($offers, false);
    }

    public function label(): string
    {
        return match ($this->status) {
            StockStatus::InStock => $this->confirmed ? 'În stoc' : 'Stoc la furnizor',
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

    /** "Livrare în 2–4 zile lucrătoare", from the best source, when its supplier gave a window. */
    public function deliveryWindow(): ?string
    {
        $days = $this->sources[0]['delivery'] ?? null;

        return $days === null ? null : 'Livrare în '.self::range($days).' zile lucrătoare';
    }

    /** "În 2–4 zile lucrătoare": the range alone, for a line that already says "Livrare". */
    public function deliveryRange(): ?string
    {
        $days = $this->sources[0]['delivery'] ?? null;

        return $days === null ? null : 'În '.self::range($days).' zile lucrătoare';
    }

    /** The line on a product card: "În stoc · livrare în 2–4 zile". */
    public function shortDelivery(): ?string
    {
        $days = $this->sources[0]['delivery'] ?? null;
        $stocked = in_array($this->status, [StockStatus::InStock, StockStatus::LowStock], true);

        return match (true) {
            $days !== null => ($stocked ? 'În stoc · ' : '').'livrare în '.self::range($days).' zile',
            $stocked => 'În stoc',
            default => null,
        };
    }

    /**
     * The countries the part can be had from, for the list under the price.
     *
     * @return list<array{country: ?string, status: StockStatus, delivery: array{0: int, 1: int}|null}>
     */
    public function stockedSources(): array
    {
        return array_values(array_filter(
            $this->sources,
            fn (array $source): bool => in_array($source['status'], [StockStatus::InStock, StockStatus::LowStock, StockStatus::Backorder], true),
        ));
    }

    public static function sourceLabel(StockStatus $status): string
    {
        return match ($status) {
            StockStatus::InStock => 'Stoc disponibil',
            StockStatus::LowStock => 'Stoc limitat',
            StockStatus::Backorder => 'La comandă',
            default => 'Stoc neconfirmat',
        };
    }

    /** @param  array{0: int, 1: int}  $days */
    public static function range(array $days): string
    {
        return $days[0] === $days[1] ? (string) $days[0] : $days[0].'–'.$days[1];
    }

    /** @param  Collection<int, SupplierOffer>  $offers */
    private static function fromOffers(Collection $offers, bool $confirmed): self
    {
        if ($offers->isEmpty()) {
            return new self(StockStatus::Unknown, null, null, null);
        }

        // ->value, not a string cast: the column is cast to an enum, and casting a backed enum
        // to string is a fatal error rather than the value.
        $ranked = $offers->sortBy(fn (SupplierOffer $offer): int => self::PRIORITY[$offer->stock_status?->value ?? ''] ?? 9)->values();
        $best = $ranked->first();

        // One line per country, each with the best offer from there; the order stays best first.
        $sources = $ranked
            ->groupBy(fn (SupplierOffer $offer): string => self::countryOf($offer) ?? '')
            ->map(fn (Collection $group, string $country): array => [
                'country' => $country === '' ? null : $country,
                'status' => $group->first()->stock_status ?? StockStatus::Unknown,
                'delivery' => self::deliveryDays($group->first(), $country === '' ? null : $country),
            ])
            ->values()
            ->all();

        return new self(
            $best->stock_status ?? StockStatus::Unknown,
            $best->stock_quantity === null ? null : (int) $best->stock_quantity,
            $best->dispatch_days_min === null ? null : (int) $best->dispatch_days_min,
            $best->dispatch_days_max === null ? null : (int) $best->dispatch_days_max,
            $sources,
            $confirmed,
        );
    }

    private static function countryOf(SupplierOffer $offer): ?string
    {
        $code = $offer->warehouse?->country_code ?: $offer->supplierProduct?->supplier?->country_code;

        return $code ? strtoupper((string) $code) : null;
    }

    /** @return array{0: int, 1: int}|null working days from order to delivery, null when the supplier gave no window */
    private static function deliveryDays(SupplierOffer $offer, ?string $country): ?array
    {
        $min = $offer->dispatch_days_min ?? $offer->dispatch_days_max ?? $offer->lead_time_days;
        $max = $offer->dispatch_days_max ?? $offer->dispatch_days_min ?? $offer->lead_time_days;

        if ($min === null) {
            return null;
        }

        $transit = (array) config('emud.delivery.transit_days', []);
        [$from, $to] = $transit[$country ?? ''] ?? $transit['*'] ?? [2, 4];

        return [(int) $min + (int) $from, (int) $max + (int) $to];
    }
}
