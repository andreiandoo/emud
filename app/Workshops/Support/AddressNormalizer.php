<?php

namespace App\Workshops\Support;

/**
 * Romanian addresses as typed by thousands of different clerks, made comparable.
 *
 * normalize() expands abbreviations to one spelling ("Șos." and "SOSEAUA" both become
 * "soseaua") for search and display-independent storage. fingerprint() goes further and keeps
 * only what locates the building (street name, numbers, locality), which is how
 * "Botoşani, Str. Pacea nr. 90, jud. BOTOŞANI" and "STRADA PACEA NR. 90, BOTOSANI" are seen to
 * be the same place.
 */
class AddressNormalizer
{
    private const CANONICAL = [
        'str' => 'strada', 'strada' => 'strada',
        'bd' => 'bulevardul', 'bdul' => 'bulevardul', 'bld' => 'bulevardul', 'blvd' => 'bulevardul',
        'bulevard' => 'bulevardul', 'bulevardul' => 'bulevardul',
        'sos' => 'soseaua', 'sosea' => 'soseaua', 'soseaua' => 'soseaua',
        'cal' => 'calea', 'calea' => 'calea',
        'al' => 'aleea', 'aleea' => 'aleea', 'intr' => 'intrarea', 'intrarea' => 'intrarea',
        'pta' => 'piata', 'piata' => 'piata', 'spl' => 'splaiul', 'splaiul' => 'splaiul',
        'nr' => 'nr', 'numar' => 'nr', 'numarul' => 'nr',
        'jud' => 'judet', 'judet' => 'judet', 'judetul' => 'judet',
        'com' => 'comuna', 'comuna' => 'comuna', 'sat' => 'sat', 'satul' => 'sat',
        'mun' => 'municipiul', 'municipiul' => 'municipiul', 'municipiu' => 'municipiul',
        'oras' => 'oras', 'orasul' => 'oras', 'loc' => 'localitatea', 'localitatea' => 'localitatea',
        'sect' => 'sector', 'sector' => 'sector', 'sectorul' => 'sector',
        'bl' => 'bloc', 'bloc' => 'bloc', 'sc' => 'scara', 'scara' => 'scara',
        'et' => 'etaj', 'etaj' => 'etaj', 'ap' => 'ap', 'apartament' => 'ap',
        'cam' => 'camera', 'camera' => 'camera',
    ];

    /** Words that describe the kind of thing, not which one. */
    private const NOISE = [
        'strada', 'bulevardul', 'soseaua', 'calea', 'aleea', 'intrarea', 'piata', 'splaiul',
        'nr', 'judet', 'comuna', 'sat', 'municipiul', 'oras', 'localitatea', 'sector', 'bloc',
        'scara', 'etaj', 'ap', 'camera', 'romania', 'hala', 'pct', 'punct', 'punctul', 'lucru',
        'de', 'la', 'din', 'cu', 'fn', 'f', 'n', 'cf', 'cod', 'postal', 'zona', 'parter',
    ];

    public static function normalize(mixed $address): string
    {
        $folded = TextNormalizer::fold($address);

        if ($folded === '') {
            return '';
        }

        // "B-dul" folds to two words.
        $folded = preg_replace('/\bb dul\b/', 'bulevardul', $folded) ?? $folded;

        $tokens = array_map(
            fn (string $token): string => self::CANONICAL[$token] ?? $token,
            explode(' ', $folded),
        );

        return implode(' ', $tokens);
    }

    /**
     * The words that locate the building, deduplicated and sorted. The county's own name is
     * dropped because one clerk writes it and the next does not.
     */
    public static function fingerprint(mixed $address, ?string $countyCode = null): string
    {
        $countyWords = $countyCode !== null && ($name = RomanianCounties::name($countyCode)) !== null
            ? explode(' ', TextNormalizer::fold($name))
            : [];

        $tokens = array_filter(
            explode(' ', self::normalize($address)),
            fn (string $token): bool => $token !== ''
                && ! in_array($token, self::NOISE, true)
                && ! in_array($token, $countyWords, true)
                && (strlen($token) > 1 || ctype_digit($token)),
        );

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * 0 to 1. House numbers must agree when both addresses give one. Beyond that, one clerk adds
     * "(HALA REPARAȚII)" and the next does not, so an address whose every locating word appears
     * in the other counts almost as much as an identical one, as long as it names at least a
     * street and a number.
     */
    public static function similarity(mixed $a, mixed $b, ?string $countyCode = null): float
    {
        $left = self::fingerprint($a, $countyCode);
        $right = self::fingerprint($b, $countyCode);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        $numbersLeft = self::numbers($left);
        $numbersRight = self::numbers($right);

        if ($numbersLeft !== [] && $numbersRight !== [] && array_intersect($numbersLeft, $numbersRight) === []) {
            return 0.0;
        }

        ['matched' => $matched, 'left' => $leftCount, 'right' => $rightCount] = TokenSimilarity::overlap($left, $right);
        $jaccard = TokenSimilarity::compare($left, $right);
        $smaller = min($leftCount, $rightCount);
        $contained = $smaller >= 2 ? round(0.95 * $matched / $smaller, 4) : 0.0;

        return max($jaccard, $contained);
    }

    /** @return list<string> */
    private static function numbers(string $fingerprint): array
    {
        return array_values(array_filter(explode(' ', $fingerprint), fn (string $token): bool => preg_match('/\d/', $token) === 1));
    }
}
