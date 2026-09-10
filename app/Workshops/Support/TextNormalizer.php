<?php

namespace App\Workshops\Support;

use Illuminate\Support\Str;

/**
 * Source text made comparable without losing what it said.
 *
 * clean() only repairs what transport broke: Windows-1250 bytes posing as UTF-8, UTF-8 read back
 * through a Windows-1252 decoder, HTML entities, the cedilla ş/ţ that older Romanian systems use
 * for ș/ț, and runs of whitespace. It keeps diacritics and case, because what it returns is what
 * an operator reads. fold() is for comparison only: lowercase ASCII words and nothing else.
 */
class TextNormalizer
{
    private const CEDILLA_TO_COMMA = ['ş' => 'ș', 'Ş' => 'Ș', 'ţ' => 'ț', 'Ţ' => 'Ț'];

    public static function clean(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = self::toUtf8((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strtr($value, self::CEDILLA_TO_COMMA);
        $value = preg_replace('/[\p{Cc}\p{Zs}\x{200B}\x{FEFF}]+/u', ' ', $value) ?? $value;
        $value = trim($value, ' ,;');

        return $value === '' ? null : $value;
    }

    /**
     * Lowercase ASCII words separated by single spaces. "Șos. Cristianului nr. 6" and
     * "SOS CRISTIANULUI NR 6" fold to the same string.
     */
    public static function fold(mixed $value): string
    {
        $value = self::clean($value);

        if ($value === null) {
            return '';
        }

        return Str::of($value)->ascii('ro')->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    /**
     * Title case for values a source sends in capitals ("SAT LACU SĂRAT"), left alone when it
     * already has lowercase letters, since a deliberate "McLaren" is not ours to change.
     */
    public static function titleIfShouting(mixed $value): ?string
    {
        $value = self::clean($value);

        if ($value === null || preg_match('/\p{Ll}/u', $value) === 1) {
            return $value;
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public static function toUtf8(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            // Windows-1250 is what Romanian legacy systems emit. ISO-8859-2 places ă, â, î, ș and
            // ț at the same bytes, so it is a faithful fallback where iconv is missing.
            $converted = function_exists('iconv') ? @iconv('WINDOWS-1250', 'UTF-8//IGNORE', $value) : false;
            $value = is_string($converted) && $converted !== ''
                ? $converted
                : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2');
        }

        return self::repairDoubleEncoding($value);
    }

    /**
     * UTF-8 that went through a Windows-1252 decoder on its way here: "È™" for "ș", "Äƒ" for
     * "ă". Reversed only when every character survives the round trip and the result is valid
     * UTF-8, so a genuine "Ä" in a German brand name is left as it is.
     */
    private static function repairDoubleEncoding(string $value): string
    {
        if (preg_match('/[\x{00C2}-\x{00C5}\x{00C8}][\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}\x{2014}\x{2018}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]/u', $value) !== 1) {
            return $value;
        }

        $bytes = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');

        if (mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252') !== $value) {
            return $value;
        }

        return mb_check_encoding($bytes, 'UTF-8') ? $bytes : $value;
    }
}
