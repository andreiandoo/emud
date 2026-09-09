<?php

namespace App\Commerce;

use App\Models\ExchangeRate;
use App\Settings\StoreSettings;
use Illuminate\Support\Carbon;

/**
 * Converts supplier money into the store's base currency.
 *
 * Suppliers quote in EUR, PLN, CZK and USD. Comparing two offers without applying
 * a rate is not a rounding problem, it picks the wrong supplier. The original
 * amount and currency are never replaced: this only produces the converted value
 * plus the rate and the day it came from, so a past decision stays reproducible.
 *
 * Rates are read from the database only. Fetching them is a separate, scheduled
 * concern, so nothing here can make a network call while an import is running.
 */
class CurrencyConverter
{
    /** @var array<string, array{rate: float, date: string}|null> */
    private array $memo = [];

    /**
     * The admin setting wins over the config default. Reading only the config meant the shop
     * currency chosen in /admin/settings was ignored, so prices stayed in whatever currency the
     * supplier's feed happened to use.
     */
    public function baseCurrency(): string
    {
        $configured = (string) config('emud.catalog.default_currency', 'RON');

        return strtoupper(app(StoreSettings::class)->string('default_currency', $configured) ?: $configured);
    }

    /**
     * @return array{amount: float, rate: float, rate_date: string, currency: string}|null
     *                                                                                     Null when no rate is known, which callers must treat as "cannot compare",
     *                                                                                     never as a rate of 1.
     */
    public function convert(float $amount, string $from, ?string $to = null, ?Carbon $on = null): ?array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to ?? $this->baseCurrency()));

        if ($from === '' || $to === '') {
            return null;
        }

        if ($from === $to) {
            return ['amount' => $amount, 'rate' => 1.0, 'rate_date' => ($on ?? now())->toDateString(), 'currency' => $to];
        }

        $rate = $this->rate($from, $to, $on);

        if (! $rate) {
            return null;
        }

        return [
            'amount' => round($amount * $rate['rate'], 4),
            'rate' => $rate['rate'],
            'rate_date' => $rate['date'],
            'currency' => $to,
        ];
    }

    /** @return array{rate: float, date: string}|null */
    public function rate(string $from, string $to, ?Carbon $on = null): ?array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        $on ??= now();
        $key = "{$from}:{$to}:{$on->toDateString()}";

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->resolve($from, $to, $on);
    }

    /** @return array{rate: float, date: string}|null */
    private function resolve(string $from, string $to, Carbon $on): ?array
    {
        if ($from === $to) {
            return ['rate' => 1.0, 'date' => $on->toDateString()];
        }

        if ($direct = $this->lookup($from, $to, $on)) {
            return $direct;
        }

        // The National Bank publishes everything against RON, so EUR→RON exists but
        // RON→EUR does not. Inverting a published rate is exact enough here; the
        // spread only matters when actually buying currency.
        if ($inverse = $this->lookup($to, $from, $on)) {
            return ['rate' => round(1 / $inverse['rate'], 8), 'date' => $inverse['date']];
        }

        // PLN→CZK is never published directly. Both legs exist against the base, so
        // the cross rate is derived rather than left unanswered.
        $base = $this->baseCurrency();

        if ($from === $base || $to === $base) {
            return null;
        }

        $fromLeg = $this->resolve($from, $base, $on);
        $toLeg = $this->resolve($base, $to, $on);

        if (! $fromLeg || ! $toLeg) {
            return null;
        }

        return [
            'rate' => round($fromLeg['rate'] * $toLeg['rate'], 8),
            'date' => min($fromLeg['date'], $toLeg['date']),
        ];
    }

    /** @return array{rate: float, date: string}|null */
    private function lookup(string $from, string $to, Carbon $on): ?array
    {
        // Most recent rate on or before the requested day: a weekend or a bank
        // holiday must not leave an import without a rate.
        $rate = ExchangeRate::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->whereDate('rate_date', '<=', $on->toDateString())
            ->orderByDesc('rate_date')
            ->first();

        return $rate ? ['rate' => (float) $rate->rate, 'date' => $rate->rate_date->toDateString()] : null;
    }
}
