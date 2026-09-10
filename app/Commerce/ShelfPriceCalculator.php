<?php

namespace App\Commerce;

use App\Models\SupplierOffer;

/**
 * The price a customer sees for an offer, under a pricing rule.
 *
 * Three numbers are computed and the highest wins: the rule's target margin on the landed
 * cost, the price that still leaves the minimum contribution, and the supplier's advertised
 * minimum. Which one decided the price is reported, because "why is this 40 lei dearer than
 * the margin says" is always one of the two floors.
 */
final class ShelfPriceCalculator
{
    public function __construct(
        private readonly ContributionMarginCalculator $margins,
        private readonly CurrencyConverter $converter,
    ) {}

    /**
     * @param  array<string, mixed>  $landed  From LandedCostCalculator, for one unit.
     * @param  array<string, mixed>  $policy  From PricingPolicy.
     * @return array{price: float, currency: string, binding: string, floors: array<string, float>, contribution: float, contribution_percent: ?float}
     */
    public function price(SupplierOffer $offer, array $landed, array $policy): array
    {
        $vatRate = (float) ($offer->vat_rate ?? config('emud.catalog.default_vat_rate'));
        // Capped below 100%: a margin at or above it has no price, only a division by zero.
        $margin = min(max((float) $policy['target_margin_percent'], 0.0), 90.0) / 100;

        $floors = array_filter([
            'target_margin' => $landed['unit_landed_cost'] / (1 - $margin) * (1 + $vatRate / 100),
            'minimum_contribution' => $this->margins->minimumGrossPrice($offer, (float) $policy['minimum_contribution_percent']),
            'map' => $this->mapInBaseCurrency($offer),
        ], static fn (?float $value): bool => $value !== null);

        $binding = array_search(max($floors), $floors, true);
        $price = $this->roundUp(max($floors));
        $result = $this->margins->for($offer, $price);

        return [
            'price' => $price,
            'currency' => $landed['currency'],
            'binding' => (string) $binding,
            'floors' => array_map(static fn (float $value): float => round($value, 2), $floors),
            'contribution' => $result['contribution'],
            'contribution_percent' => $result['contribution_percent'],
        ];
    }

    /**
     * MAP is treated as the customer-facing price, VAT included, in the supplier's currency.
     * Without a rate it cannot be compared, and is left out rather than guessed at.
     */
    private function mapInBaseCurrency(SupplierOffer $offer): ?float
    {
        if ($offer->map_price === null) {
            return null;
        }

        $converted = $this->converter->convert((float) $offer->map_price, (string) $offer->currency);

        return $converted ? (float) $converted['amount'] : null;
    }

    /**
     * Up to the next price ending in the configured cents, never down: rounding must not take
     * a price back under a floor it was just raised to.
     */
    private function roundUp(float $amount): float
    {
        $ending = (float) config('emud.pricing.price_ending', 0.99);

        if ($ending <= 0 || $ending >= 1) {
            return ceil(round($amount * 100, 6)) / 100;
        }

        // The inner round() absorbs float noise, so 100.99 does not become 101.99.
        return round(ceil(round($amount - $ending, 6)) + $ending, 2);
    }
}
