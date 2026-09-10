<?php

namespace App\Catalog\Normalization;

/**
 * Decides whether a barcode is a real GTIN before anything is matched on it.
 *
 * An EAN match scores 100 and maps automatically, so a bad one does the most damage
 * of any identifier. Feeds routinely carry placeholders — 0000000000000,
 * 1111111111111, a truncated number, a SKU pasted into the EAN column — and a
 * placeholder shared by two hundred articles would otherwise map all two hundred
 * onto whichever catalogue part happens to hold it.
 */
class GtinValidator
{
    private const LENGTHS = [8, 12, 13, 14];

    public function isValid(?string $value): bool
    {
        return $this->canonical($value) !== null;
    }

    /**
     * The fourteen-digit form. EAN-8, UPC-A, EAN-13 and GTIN-14 of the same article
     * all pad to the same value, which is what makes them comparable.
     */
    public function canonical(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Spaces and hyphens are formatting. Any other character means the column
        // holds something that is not a barcode at all.
        $digits = preg_replace('/[\s-]/', '', trim($value)) ?? '';

        if ($digits === '' || ! ctype_digit($digits) || ! in_array(strlen($digits), self::LENGTHS, true)) {
            return null;
        }

        if (ltrim($digits, '0') === '' || preg_match('/^(\d)\1+$/', $digits) === 1) {
            return null;
        }

        if (! $this->checkDigitIsCorrect($digits)) {
            return null;
        }

        return str_pad($digits, 14, '0', STR_PAD_LEFT);
    }

    /**
     * Every length a catalogue source may have stored the same article under.
     *
     * @return list<string>
     */
    public function lookupForms(string $canonical): array
    {
        $forms = [$canonical];

        foreach ([13, 12, 8] as $length) {
            $surplus = 14 - $length;

            // Only a shorter form whose dropped digits are all zeros is the same
            // number; anything else would be a different article.
            if (str_repeat('0', $surplus) === substr($canonical, 0, $surplus)) {
                $forms[] = substr($canonical, $surplus);
            }
        }

        return array_values(array_unique($forms));
    }

    private function checkDigitIsCorrect(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $sum = 0;

        // Weights alternate 3, 1 starting from the digit next to the check digit,
        // which is why the body is walked right to left.
        foreach (array_reverse(str_split($body)) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($digits, -1);
    }
}
