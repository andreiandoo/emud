<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * The reason this class exists: 0.1 and 0.2 have no exact binary representation, so adding
     * them as floats does not give 0.3. Integer minor units do.
     */
    public function test_addition_that_float_gets_wrong_is_exact(): void
    {
        $sum = Money::of('0.10', 'RON')->plus(Money::of('0.20', 'RON'));

        $this->assertSame('0.30', $sum->toDecimal());
        $this->assertSame(30, $sum->toMinor());
    }

    public function test_a_long_order_does_not_drift(): void
    {
        $line = Money::of('0.07', 'RON');
        $total = Money::zero('RON');

        for ($i = 0; $i < 1000; $i++) {
            $total = $total->plus($line);
        }

        $this->assertSame('70.00', $total->toDecimal());
    }

    public function test_multiplication_stays_exact(): void
    {
        $this->assertSame('749.85', Money::of('249.95', 'RON')->times(3)->toDecimal());
    }

    public function test_parsing_never_passes_through_a_float(): void
    {
        $this->assertSame(24995, Money::of('249.95', 'RON')->toMinor());
        $this->assertSame(24995, Money::of(249.95, 'RON')->toMinor());
        $this->assertSame(24900, Money::of(249, 'RON')->toMinor());
    }

    public function test_a_decimal_column_value_round_trips(): void
    {
        foreach (['0.00', '0.01', '19.99', '1234.56', '-45.10'] as $value) {
            $this->assertSame($value, Money::of($value, 'RON')->toDecimal());
        }
    }

    public function test_a_missing_or_empty_amount_is_zero(): void
    {
        $this->assertTrue(Money::of(null, 'RON')->isZero());
        $this->assertTrue(Money::of('', 'RON')->isZero());
    }

    public function test_currencies_without_minor_units_are_respected(): void
    {
        $this->assertSame(1500, Money::of('1500', 'JPY')->toMinor());
        $this->assertSame('1500', Money::of('1500', 'JPY')->toDecimal());
    }

    /**
     * Combining currencies silently would produce a total that means nothing, so it is refused.
     */
    public function test_mixing_currencies_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('10.00', 'RON')->plus(Money::of('10.00', 'EUR'));
    }

    public function test_garbage_is_refused_rather_than_read_as_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('douăzeci de lei', 'RON');
    }

    public function test_vat_is_rounded_half_up(): void
    {
        // 19.99 at 21% is 4.1979, which must present as 4.20 rather than 4.19.
        $this->assertSame('4.20', Money::of('19.99', 'RON')->percentage(21)->toDecimal());
        $this->assertSame('0.03', Money::of('0.50', 'RON')->percentage(5)->toDecimal());
    }

    public function test_comparison_at_the_threshold_is_inclusive(): void
    {
        $threshold = Money::of('300.00', 'RON');

        $this->assertTrue(Money::of('300.00', 'RON')->isGreaterThanOrEqualTo($threshold));
        $this->assertFalse(Money::of('299.99', 'RON')->isGreaterThanOrEqualTo($threshold));
    }

    public function test_formatting_groups_thousands_and_keeps_the_sign(): void
    {
        $this->assertSame('1.234,56 RON', Money::of('1234.56', 'RON')->format());
        $this->assertSame('-0,50 RON', Money::of('-0.50', 'RON')->format());
    }

    public function test_summing_an_empty_list_gives_zero_in_the_right_currency(): void
    {
        $total = Money::sum([], 'EUR');

        $this->assertTrue($total->isZero());
        $this->assertSame('EUR', $total->currency);
    }
}
