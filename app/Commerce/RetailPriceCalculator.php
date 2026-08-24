<?php

namespace App\Commerce;

class RetailPriceCalculator
{
    public function fromCost(float $netCost, ?float $vatRate = null, ?float $markupPercent = null): float
    {
        $vatRate ??= config('emud.catalog.default_vat_rate');
        $markupPercent ??= config('emud.pricing.default_markup_percent');
        $gross = $netCost * (1 + $markupPercent / 100) * (1 + $vatRate / 100);
        $ending = (float) config('emud.pricing.price_ending', 0.99);

        return floor($gross) + $ending;
    }
}
