<?php

namespace App\Commerce;

use App\Enums\ShippingClass;
use App\Models\SupplierOffer;

/**
 * What is left after selling one line at a given price.
 *
 * Gross margin flatters a dropship catalogue. A skid plate at 40% gross margin
 * with expensive reverse freight can contribute less than a switch panel at 50%
 * that ships in an envelope, so payment fees and expected return and warranty
 * cost are subtracted here rather than assumed away.
 *
 * Every input is persisted or configured, so the figure can be recomputed when
 * costs or shipping rules change instead of being frozen at import time.
 */
class ContributionMarginCalculator
{
    public function __construct(private readonly LandedCostCalculator $landedCost) {}

    /**
     * @param  float  $sellingPriceGross  Customer-facing price, VAT included.
     * @return array{
     *     currency: string,
     *     quantity: int,
     *     net_revenue: float,
     *     vat: float,
     *     landed_cost: float,
     *     payment_fee: float,
     *     return_reserve: float,
     *     warranty_reserve: float,
     *     contribution: float,
     *     contribution_percent: ?float,
     *     gross_margin: float,
     *     gross_margin_percent: ?float,
     *     landed_cost_breakdown: array<string, mixed>
     * }
     */
    public function for(SupplierOffer $offer, float $sellingPriceGross, int $quantity = 1, ?float $freightOverride = null): array
    {
        $quantity = max(1, $quantity);
        $landed = $this->landedCost->for($offer, $quantity, $freightOverride);

        $vatRate = (float) ($offer->vat_rate ?? config('emud.catalog.default_vat_rate'));
        $grossRevenue = $sellingPriceGross * $quantity;
        $netRevenue = $vatRate > 0 ? $grossRevenue / (1 + $vatRate / 100) : $grossRevenue;

        $margins = config('emud.pricing.margins');

        // The processor takes its cut of the amount the customer actually pays, VAT
        // included, not of our net revenue.
        $paymentFee = $grossRevenue * ((float) $margins['payment_fee_percent'] / 100) + (float) $margins['payment_fee_fixed'];

        // Cost of one return, then weighted by how often it happens. Bulky classes
        // cost several times more to get back than to send out, and the supplier's
        // restocking fee lands on us too.
        $restockingPercent = (float) ($offer->supplierProduct?->supplier?->restocking_fee_percent ?? 0);
        $returnCost = $landed['freight'] * $this->reverseFreightMultiplier($offer, $margins)
            + (float) $margins['return_handling_cost']
            + $landed['product_cost'] * ($restockingPercent / 100);
        $returnReserve = ((float) $margins['return_rate_percent'] / 100) * $returnCost;

        $warrantyReserve = $landed['total'] * ((float) $margins['warranty_reserve_percent'] / 100);

        $grossMargin = $netRevenue - $landed['product_cost'];
        $contribution = $netRevenue - $landed['total'] - $paymentFee - $returnReserve - $warrantyReserve;

        return [
            'currency' => $landed['currency'],
            'quantity' => $quantity,
            'net_revenue' => round($netRevenue, 2),
            'vat' => round($grossRevenue - $netRevenue, 2),
            'landed_cost' => $landed['total'],
            'payment_fee' => round($paymentFee, 2),
            'return_reserve' => round($returnReserve, 2),
            'warranty_reserve' => round($warrantyReserve, 2),
            'contribution' => round($contribution, 2),
            'contribution_percent' => $netRevenue > 0 ? round($contribution / $netRevenue * 100, 2) : null,
            'gross_margin' => round($grossMargin, 2),
            'gross_margin_percent' => $netRevenue > 0 ? round($grossMargin / $netRevenue * 100, 2) : null,
            'landed_cost_breakdown' => $landed,
        ];
    }

    /** @param array<string, mixed> $margins */
    private function reverseFreightMultiplier(SupplierOffer $offer, array $margins): float
    {
        return $offer->shipping_class instanceof ShippingClass && $offer->shipping_class->hasPunishingReturns()
            ? (float) $margins['bulky_return_multiplier']
            : 1.0;
    }
}
