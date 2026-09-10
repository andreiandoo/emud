<?php

namespace App\Workshops\Support;

use App\Workshops\Data\PhoneNumber;

/**
 * Telephone numbers as sources write them ("0722 123 456", "+40 722 123 456", "0040722…",
 * "0212422744, 0722214506") reduced to E.164.
 *
 * Romanian numbers are recognised by shape: nine digits after the country code, starting with 7
 * (mobile), 2 or 3 (landline), 8 or 9 (free and premium lines). Anything written with an explicit
 * foreign prefix is kept as an international number rather than forced into a Romanian one.
 * A string that fits none of these is not a number, and nothing is guessed.
 */
class PhoneNormalizer
{
    /** @return list<PhoneNumber> */
    public static function extractAll(mixed $raw): array
    {
        $raw = TextNormalizer::clean($raw);

        if ($raw === null) {
            return [];
        }

        $numbers = [];

        foreach (preg_split('/\s*[,;\/|]\s*|\s+(?:sau|si|și|or)\s+/iu', $raw) ?: [] as $piece) {
            foreach (self::normalizeWithSplit($piece) as $number) {
                $numbers[$number->e164] ??= $number;
            }
        }

        return array_values($numbers);
    }

    public static function normalize(mixed $raw): ?PhoneNumber
    {
        $raw = TextNormalizer::clean($raw);

        if ($raw === null) {
            return null;
        }

        $core = preg_replace('/\s*\(?\b(?:int|interior|ext)\b\.?\s*\d+\)?\s*$/iu', '', $raw) ?? $raw;
        $explicitPlus = str_starts_with(ltrim($core), '+');
        $digits = preg_replace('/\D+/', '', $core) ?? '';

        if ($digits === '') {
            return null;
        }

        $international = match (true) {
            $explicitPlus => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            strlen($digits) === 11 && str_starts_with($digits, '40') && in_array($digits[2], ['2', '3', '7', '8', '9'], true) => $digits,
            strlen($digits) === 10 && $digits[0] === '0' => '40'.substr($digits, 1),
            strlen($digits) === 9 && in_array($digits[0], ['2', '3', '7'], true) => '40'.$digits,
            default => null,
        };

        if ($international === null) {
            return null;
        }

        if (str_starts_with($international, '40')) {
            $national = substr($international, 2);

            // "+40 0722 123 456": the trunk zero kept after the country code.
            if (strlen($national) === 10 && $national[0] === '0') {
                $national = substr($national, 1);
            }

            if (strlen($national) !== 9) {
                return null;
            }

            $type = match ($national[0]) {
                '7' => 'mobile',
                '2', '3' => 'landline',
                '8', '9' => 'special',
                default => null,
            };

            return $type === null ? null : new PhoneNumber($raw, '+40'.$national, $type);
        }

        if (strlen($international) < 8 || strlen($international) > 15) {
            return null;
        }

        return new PhoneNumber($raw, '+'.$international, 'international');
    }

    /**
     * Two numbers typed with only a space between them ("0722123456 0733123456") read as twenty
     * digits; they are split rather than discarded.
     *
     * @return list<PhoneNumber>
     */
    private static function normalizeWithSplit(string $piece): array
    {
        $number = self::normalize($piece);

        if ($number !== null) {
            return [$number];
        }

        $digits = preg_replace('/\D+/', '', $piece) ?? '';

        if (strlen($digits) !== 20 || $digits[0] !== '0' || $digits[10] !== '0') {
            return [];
        }

        return array_values(array_filter([
            self::normalize(substr($digits, 0, 10)),
            self::normalize(substr($digits, 10)),
        ]));
    }
}
