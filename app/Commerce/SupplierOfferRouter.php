<?php

namespace App\Commerce;

use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;

/**
 * Decides which supplier fulfils a line, and says why.
 *
 * Replaces BestSupplierOffer, which ranked on the price a supplier quotes and was never
 * called. The quoted price is the wrong thing to rank on: a part a few lei cheaper at a
 * supplier charging a dropship fee and freight lands dearer than one that ships free.
 *
 * Offers are compared on an effective cost: the landed cost, raised by a small penalty
 * for being on backorder or slow to dispatch. That is the whole policy, stated as
 * "how much more would we pay to have it sooner", and the penalties are configuration.
 * No weighted score nobody can explain afterwards.
 */
final class SupplierOfferRouter
{
    private const SELLABLE = [StockStatus::InStock, StockStatus::LowStock, StockStatus::Backorder];

    public function __construct(private readonly LandedCostCalculator $landed) {}

    public function route(Product $product, int $quantity = 1, ?string $destinationCountry = null, ?ProductVariant $variant = null): RoutingResult
    {
        $quantity = max(1, $quantity);
        $forThisLine = fn ($query) => $query
            ->where('product_id', $product->id)
            ->when($variant, fn ($inner) => $inner->where(fn ($v) => $v->whereNull('variant_id')->orWhere('variant_id', $variant->id)));

        $soldThroughSuppliers = SupplierProduct::query()->where($forThisLine)->whereHas('offer')->exists();

        $offers = SupplierOffer::query()
            ->routable()
            ->with('supplierProduct.supplier')
            ->whereHas('supplierProduct', $forThisLine)
            ->get();

        $eligible = collect();
        $excluded = [];

        foreach ($offers as $offer) {
            $supplier = $offer->supplierProduct->supplier;
            $reason = $this->exclusionReason($offer, $quantity, $destinationCountry);

            if ($reason !== null) {
                $excluded[] = ['supplier' => $supplier->code, 'reason' => $reason];

                continue;
            }

            $landed = $this->landed->for($offer, $quantity);
            [$effective, $adjustments] = $this->effectiveCost($offer, $landed);

            $eligible->push([
                'offer' => $offer,
                'supplier' => $supplier,
                'supplier_product' => $offer->supplierProduct,
                'landed' => $landed,
                'effective_unit_cost' => $effective,
                'adjustments' => $adjustments,
            ]);
        }

        $ranked = $eligible
            // An offer that cannot be costed never beats one that can: without a rate or a
            // cost, "cheapest" is a guess. Supplier priority only breaks exact ties.
            ->sort(fn (array $a, array $b): int => [! $a['landed']['complete'], $a['effective_unit_cost'], $a['supplier']->priority, $a['offer']->id]
                <=> [! $b['landed']['complete'], $b['effective_unit_cost'], $b['supplier']->priority, $b['offer']->id])
            ->values();

        return new RoutingResult($ranked, $excluded, $soldThroughSuppliers);
    }

    /** Hard rules. Any one of them makes the offer unusable for this line, whatever it costs. */
    private function exclusionReason(SupplierOffer $offer, int $quantity, ?string $destinationCountry): ?string
    {
        $supplier = $offer->supplierProduct->supplier;

        if (! in_array($offer->stock_status, self::SELLABLE, true)) {
            return 'stock_'.($offer->stock_status?->value ?? 'unknown');
        }

        // A known quantity below the order is a refusal unless the supplier takes backorders.
        if ($offer->stock_quantity !== null && (int) $offer->stock_quantity < $quantity && $offer->stock_status !== StockStatus::Backorder) {
            return 'insufficient_quantity';
        }

        if ($offer->hasArticleLevelDropshipBlock()) {
            return 'article_not_dropshippable';
        }

        if ($destinationCountry !== null && ! $supplier->shipsTo($destinationCountry)) {
            return 'destination_not_served';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $landed
     * @return array{0: float, 1: list<string>}
     */
    private function effectiveCost(SupplierOffer $offer, array $landed): array
    {
        $routing = config('emud.suppliers.routing', []);
        $penaltyPercent = 0.0;
        $adjustments = [];

        $stockPenalty = match ($offer->stock_status) {
            StockStatus::Backorder => (float) ($routing['backorder_penalty_percent'] ?? 8),
            StockStatus::LowStock => (float) ($routing['low_stock_penalty_percent'] ?? 2),
            default => 0.0,
        };

        if ($stockPenalty > 0) {
            $penaltyPercent += $stockPenalty;
            $adjustments[] = $offer->stock_status->value.' +'.$stockPenalty.'%';
        }

        // The slow end of the window is what the customer risks waiting for. An offer that
        // states no window is treated as slow rather than as instant.
        $days = $offer->dispatch_days_max ?? $offer->dispatch_days_min ?? $offer->lead_time_days
            ?? (int) ($routing['unknown_dispatch_days'] ?? 5);
        $dispatchPenalty = round((int) $days * (float) ($routing['dispatch_day_penalty_percent'] ?? 0.5), 2);

        if ($dispatchPenalty > 0) {
            $penaltyPercent += $dispatchPenalty;
            $adjustments[] = "dispatch {$days}d +{$dispatchPenalty}%";
        }

        return [round($landed['unit_landed_cost'] * (1 + $penaltyPercent / 100), 4), $adjustments];
    }
}
