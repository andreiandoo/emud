<?php

namespace Tests\Unit;

use App\Commerce\RetailPriceCalculator;
use Tests\TestCase;

class RetailPriceCalculatorTest extends TestCase
{
    public function test_it_applies_markup_vat_and_commercial_ending(): void
    {
        $price = app(RetailPriceCalculator::class)->fromCost(100, 21, 25);
        $this->assertSame(151.99, $price);
    }
}
