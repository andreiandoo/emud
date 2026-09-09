<?php

namespace App\Commerce;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use RuntimeException;
use SimpleXMLElement;

/**
 * Parses published reference rates into exchange_rates.
 *
 * Two documents are understood. The National Bank of Romania quotes everything against RON, one
 * Rate element per currency, sometimes per 100 units. The European Central Bank quotes everything
 * against the euro, one Cube per currency, and its rates are cross-multiplied here into the
 * store's own currency.
 *
 * Rows are always stored the same way regardless of source: base = foreign currency, quote = the
 * store's currency, rate = what one unit is worth. CurrencyConverter derives every other pair
 * from that shape, so a document that stored them any other way would break cross rates.
 *
 * Parsing is namespace-agnostic and tolerant on purpose: if a bank changes its document, this
 * must fail with a clear message rather than silently write nonsense into every margin
 * calculation downstream.
 */
class ExchangeRateImporter
{
    public const SOURCE = 'bnr';

    public const SOURCE_ECB = 'ecb';

    public function __construct(private readonly CurrencyConverter $converter) {}

    /** @return array{date: string, imported: int, skipped: int} */
    public function importFromXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new RuntimeException('Documentul de cursuri valutare nu a putut fi parsat.');
        }

        // The two documents are told apart by shape rather than by URL, so a file passed to
        // --file is read correctly whichever bank it came from.
        return ($document->xpath('//*[local-name()="Rate"]') ?: []) !== []
            ? $this->importNationalBankDocument($document)
            : $this->importCentralBankDocument($document);
    }

    /** @return array{date: string, imported: int, skipped: int} */
    private function importNationalBankDocument(SimpleXMLElement $document): array
    {
        $cubes = $document->xpath('//*[local-name()="Cube"]') ?: [];

        if ($cubes === []) {
            throw new RuntimeException('Documentul de cursuri valutare nu conține niciun element Cube.');
        }

        $rateDate = $this->requiredDate((string) ($cubes[0]['date'] ?? ''));
        $imported = 0;
        $skipped = 0;

        foreach ($cubes[0]->xpath('.//*[local-name()="Rate"]') ?: [] as $rate) {
            $currency = strtoupper(trim((string) ($rate['currency'] ?? '')));
            $value = (float) str_replace(',', '.', trim((string) $rate));
            $multiplier = max(1, (int) ($rate['multiplier'] ?? 1));

            if (strlen($currency) !== 3 || $value <= 0) {
                $skipped++;

                continue;
            }

            $this->store($currency, 'RON', $value / $multiplier, $rateDate, self::SOURCE);
            $imported++;
        }

        return $this->result($rateDate, $imported, $skipped);
    }

    /**
     * The euro reference rates say how many units of each currency one euro buys. The store does
     * not necessarily price in euro, so each is divided through the store currency's own euro
     * rate to get what one unit is worth on the shelf.
     *
     * @return array{date: string, imported: int, skipped: int}
     */
    private function importCentralBankDocument(SimpleXMLElement $document): array
    {
        $dated = collect($document->xpath('//*[local-name()="Cube"][@time]'))->first();

        if (! $dated instanceof SimpleXMLElement) {
            throw new RuntimeException('Documentul de cursuri valutare nu conține un element Cube datat.');
        }

        $rateDate = $this->requiredDate((string) $dated['time']);
        $perEuro = ['EUR' => 1.0];
        $skipped = 0;

        foreach ($dated->xpath('.//*[local-name()="Cube"][@currency]') ?: [] as $cube) {
            $currency = strtoupper(trim((string) $cube['currency']));
            $rate = (float) $cube['rate'];

            if (strlen($currency) !== 3 || $rate <= 0) {
                $skipped++;

                continue;
            }

            $perEuro[$currency] = $rate;
        }

        $quote = $this->converter->baseCurrency();

        if (! isset($perEuro[$quote])) {
            throw new RuntimeException("Cursurile publicate nu conțin moneda magazinului ({$quote}).");
        }

        $quotePerEuro = $perEuro[$quote];
        $imported = 0;

        foreach ($perEuro as $currency => $currencyPerEuro) {
            if ($currency === $quote) {
                continue;
            }

            $this->store($currency, $quote, $quotePerEuro / $currencyPerEuro, $rateDate, self::SOURCE_ECB);
            $imported++;
        }

        return $this->result($rateDate, $imported, $skipped);
    }

    private function store(string $base, string $quote, float $rate, string $rateDate, string $source): void
    {
        ExchangeRate::query()->updateOrCreate(
            ['base_currency' => $base, 'quote_currency' => $quote, 'rate_date' => $rateDate],
            ['rate' => round($rate, 8), 'source' => $source],
        );
    }

    /** @return array{date: string, imported: int, skipped: int} */
    private function result(string $rateDate, int $imported, int $skipped): array
    {
        if ($imported === 0) {
            throw new RuntimeException('Documentul de cursuri valutare nu a produs niciun curs valid.');
        }

        return ['date' => $rateDate, 'imported' => $imported, 'skipped' => $skipped];
    }

    private function requiredDate(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new RuntimeException('Documentul de cursuri valutare nu are o dată utilizabilă.');
        }

        return Carbon::parse($value)->toDateString();
    }
}
