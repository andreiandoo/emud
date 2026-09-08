<?php

namespace App\Commerce;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Parses the National Bank of Romania reference rates into exchange_rates.
 *
 * The published document is RON-denominated: each Rate element gives how many RON
 * a currency is worth, optionally for a multiplier of units (HUF and similar are
 * quoted per 100). Rows are therefore stored as base = foreign currency,
 * quote = RON, rate = value / multiplier, i.e. what one unit is worth in RON.
 *
 * Parsing is namespace-agnostic and tolerant on purpose: if the bank changes the
 * document, this must fail with a clear message rather than silently write
 * nonsense into every margin calculation downstream.
 */
class ExchangeRateImporter
{
    public const SOURCE = 'bnr';

    /** @return array{date: string, imported: int, skipped: int} */
    public function importFromXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new RuntimeException('Documentul de cursuri valutare nu a putut fi parsat.');
        }

        $cubes = $document->xpath('//*[local-name()="Cube"]') ?: [];

        if ($cubes === []) {
            throw new RuntimeException('Documentul de cursuri valutare nu conține niciun element Cube.');
        }

        $cube = $cubes[0];
        $date = (string) ($cube['date'] ?? '');

        if ($date === '' || ! $this->looksLikeDate($date)) {
            throw new RuntimeException('Elementul Cube nu are un atribut date utilizabil.');
        }

        $rateDate = Carbon::parse($date)->toDateString();
        $imported = 0;
        $skipped = 0;

        foreach ($cube->xpath('.//*[local-name()="Rate"]') ?: [] as $rate) {
            $currency = strtoupper(trim((string) ($rate['currency'] ?? '')));
            $value = (float) str_replace(',', '.', trim((string) $rate));
            $multiplier = max(1, (int) ($rate['multiplier'] ?? 1));

            if (strlen($currency) !== 3 || $value <= 0) {
                $skipped++;

                continue;
            }

            ExchangeRate::query()->updateOrCreate(
                ['base_currency' => $currency, 'quote_currency' => 'RON', 'rate_date' => $rateDate],
                ['rate' => round($value / $multiplier, 8), 'source' => self::SOURCE],
            );

            $imported++;
        }

        if ($imported === 0) {
            throw new RuntimeException('Documentul de cursuri valutare nu a produs niciun curs valid.');
        }

        return ['date' => $rateDate, 'imported' => $imported, 'skipped' => $skipped];
    }

    private function looksLikeDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
