<?php

namespace App\Workshops\Support;

class Identifiers
{
    /**
     * The fiscal code (CUI/CIF) as digits only: "RO 15428073" and "15428073" are one company.
     * ONRC writes 0 for sole traders registered before they had one; that is no code at all.
     */
    public static function cui(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/^RO\s*/', '', $value) ?? $value;
        $digits = ltrim(preg_replace('/\D+/', '', $value) ?? '', '0');

        return $digits === '' || strlen($digits) > 10 ? null : $digits;
    }

    /**
     * The control digit check ANAF applies: the digits before the last one, right-aligned under
     * the key 753217532, summed as products, times ten, modulo eleven (ten reads as zero).
     */
    public static function isValidCui(mixed $value): bool
    {
        $cui = self::cui($value);

        if ($cui === null || strlen($cui) < 2) {
            return false;
        }

        $key = '753217532';
        $body = str_pad(substr($cui, 0, -1), 9, '0', STR_PAD_LEFT);
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $body[$i] * (int) $key[$i];
        }

        $control = ($sum * 10) % 11;

        return ($control === 10 ? 0 : $control) === (int) substr($cui, -1);
    }

    /** ONRC's registration number without the whitespace variations: "J08/346/2007". */
    public static function registrationNumber(mixed $value): ?string
    {
        $value = TextNormalizer::clean($value);

        if ($value === null) {
            return null;
        }

        $value = strtoupper(preg_replace('/\s+/', '', $value) ?? $value);

        return $value === '' || $value === '-' ? null : $value;
    }
}
