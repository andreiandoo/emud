<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * An exact amount of money, held as integer minor units.
 *
 * Money was previously added and multiplied as float. Binary floating point cannot represent
 * 0.1 or 0.2 exactly, so totals drifted by a ban or two on long orders and, worse, the amount
 * charged could differ from the amount stored. Integers remove the whole class of error: the
 * decimal columns already store exact values, and this keeps the arithmetic exact too.
 *
 * Values are parsed from strings rather than floats. Casting "249.50" through float first would
 * reintroduce the very imprecision this exists to avoid.
 */
final readonly class Money
{
    /** Currencies whose minor unit is not one hundredth. Everything else uses two decimals. */
    private const EXPONENTS = ['JPY' => 0, 'KRW' => 0, 'ISK' => 0];

    private function __construct(
        public int $minor,
        public string $currency,
    ) {}

    public static function fromMinor(int $minor, string $currency): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * Accepts what a decimal column, a form field or a config value hands over. Floats are
     * accepted for compatibility with older call sites but are rendered to a fixed-precision
     * string before parsing, so they cannot smuggle in binary drift.
     */
    public static function of(string|int|float|null $amount, string $currency): self
    {
        $currency = strtoupper($currency);
        $exponent = self::exponentFor($currency);

        if ($amount === null || $amount === '') {
            return new self(0, $currency);
        }

        $text = is_float($amount) ? number_format($amount, $exponent, '.', '') : (string) $amount;
        $text = trim(str_replace([' ', ','], ['', '.'], $text));

        if (! preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $text, $matches)) {
            throw new InvalidArgumentException("Valoare monetară invalidă: {$amount}");
        }

        $whole = $matches[2] === '' ? '0' : $matches[2];
        $fraction = $matches[3] ?? '';

        // Padded and truncated rather than rounded: a value with more precision than the
        // currency supports is a bug upstream, and silently rounding it would hide it.
        $fraction = substr(str_pad($fraction, $exponent, '0'), 0, $exponent);

        $minor = (int) ($whole.$fraction);

        return new self($matches[1] === '-' ? -$minor : $minor, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /**
     * Percentages are applied to minor units and rounded half up, which is the convention
     * Romanian VAT arithmetic expects.
     */
    public function percentage(string|int|float $percent): self
    {
        $rate = self::of($percent, $this->currency)->minor;
        $numerator = $this->minor * $rate;
        $denominator = 100 * 10 ** self::exponentFor($this->currency);

        // Integer division with explicit half-up rounding rather than round(), which would put
        // the amount back through a float on its way to the answer.
        $quotient = intdiv($numerator, $denominator);
        $remainder = abs($numerator % $denominator);

        if ($remainder * 2 >= $denominator) {
            $quotient += $numerator < 0 ? -1 : 1;
        }

        return new self($quotient, $this->currency);
    }

    /** @param iterable<self> $amounts */
    public static function sum(iterable $amounts, string $currency): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor;
    }

    public function isLessThanOrEqualTo(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor <= $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    /** The exact decimal string a decimal column should store. Never a float. */
    public function toDecimal(): string
    {
        $exponent = self::exponentFor($this->currency);
        $sign = $this->minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($this->minor), $exponent + 1, '0', STR_PAD_LEFT);

        return $exponent === 0
            ? $sign.$digits
            : $sign.substr($digits, 0, -$exponent).'.'.substr($digits, -$exponent);
    }

    /** Minor units, which is what card processors expect to be charged. */
    public function toMinor(): int
    {
        return $this->minor;
    }

    /** Formatted from the exact decimal string, so display never rounds differently than storage. */
    public function format(): string
    {
        [$whole, $fraction] = array_pad(explode('.', $this->toDecimal(), 2), 2, '');
        $grouped = number_format((int) $whole, 0, ',', '.');

        // number_format drops the sign when the whole part is zero, which would print a small
        // refund as a positive amount.
        if ($this->minor < 0 && ! str_starts_with($grouped, '-')) {
            $grouped = '-'.$grouped;
        }

        return $grouped.($fraction === '' ? '' : ','.$fraction).' '.$this->currency;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private static function exponentFor(string $currency): int
    {
        return self::EXPONENTS[strtoupper($currency)] ?? 2;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Nu se pot combina sume în {$this->currency} și {$other->currency}.");
        }
    }
}
