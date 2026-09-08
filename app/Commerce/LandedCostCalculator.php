<?php

namespace App\Commerce;

use App\Models\SupplierOffer;

/**
 * What an article actually costs us delivered, in the store's base currency.
 *
 * Choosing a supplier on unit price alone is the classic way to lose money on a
 * 4x4 catalogue: a bumper three euro cheaper at a supplier charging a
 * twenty-five-euro dropship fee and oversize freight is not cheaper. Every input
 * is kept in the breakdown so a decision can be explained after the fact.
 */
class LandedCostCalculator
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    /**
     * @return array{
     *     currency: string,
     *     quantity: int,
     *     unit_cost: float,
     *     product_cost: float,
     *     dropship_fee: float,
     *     handling_fee: float,
     *     freight: float,
     *     total: float,
     *     unit_landed_cost: float,
     *     fx_rate: float,
     *     fx_rate_date: ?string,
     *     complete: bool,
     *     missing: list<string>
     * }
     */
    public function for(SupplierOffer $offer, int $quantity = 1, ?float $freightOverride = null): array
    {
        $quantity = max(1, $quantity);
        $base = $this->converter->baseCurrency();
        $missing = [];

        $rawCost = $offer->cost_price === null ? null : (float) $offer->cost_price;
        $cost = $rawCost === null ? null : $this->toBase($rawCost, $offer->currency, $base);

        if ($rawCost === null) {
            $missing[] = 'cost_price';
        } elseif ($cost === null) {
            $missing[] = 'fx_rate';
        }

        $unitCost = $cost['amount'] ?? 0.0;

        // Per-order charges are spread across the quantity actually being bought, so
        // a fee that ruins a single unit stops distorting a ten-unit line.
        $dropshipFee = $this->feeToBase($offer->dropship_fee, $offer, $base, $missing, 'dropship_fee');
        $handlingFee = $this->feeToBase($offer->handling_fee, $offer, $base, $missing, 'handling_fee');
        $freight = $freightOverride ?? $this->feeToBase($offer->shipping_cost_estimate, $offer, $base, $missing, 'freight');

        if ($freightOverride === null && $offer->shipping_cost_estimate === null) {
            $missing[] = 'freight';
        }

        $productCost = round($unitCost * $quantity, 4);
        $total = round($productCost + $dropshipFee + $handlingFee + $freight, 4);

        return [
            'currency' => $base,
            'quantity' => $quantity,
            'unit_cost' => round($unitCost, 4),
            'product_cost' => $productCost,
            'dropship_fee' => round($dropshipFee, 4),
            'handling_fee' => round($handlingFee, 4),
            'freight' => round($freight, 4),
            'total' => $total,
            'unit_landed_cost' => round($total / $quantity, 4),
            'fx_rate' => $cost['rate'] ?? 1.0,
            'fx_rate_date' => $cost['rate_date'] ?? null,
            // An incomplete landed cost is still returned, because a partial number
            // is useful in admin. It must never be presented as a decided figure.
            'complete' => $missing === [],
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /** @return array{amount: float, rate: float, rate_date: string, currency: string}|null */
    private function toBase(?float $amount, ?string $currency, string $base): ?array
    {
        if ($amount === null) {
            return null;
        }

        return $this->converter->convert($amount, $currency ?: $base, $base);
    }

    /** @param list<string> $missing */
    private function feeToBase(?string $fee, SupplierOffer $offer, string $base, array &$missing, string $label): float
    {
        if ($fee === null) {
            return 0.0;
        }

        $converted = $this->toBase((float) $fee, $offer->currency, $base);

        if (! $converted) {
            $missing[] = "{$label}_fx";

            return 0.0;
        }

        return $converted['amount'];
    }
}
