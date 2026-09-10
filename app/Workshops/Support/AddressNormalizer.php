<?php

namespace App\Workshops\Support;

/**
 * Romanian addresses as typed by thousands of different clerks, made comparable.
 *
 * normalize() expands abbreviations to one spelling ("Șos." and "SOSEAUA" both become
 * "soseaua") and writes roads and house-number letters one way ("DN 65" and "DN65" are "dn65",
 * "18 A" is "18a") for search and display-independent storage. fingerprint() goes further and
 * keeps only what locates the building (street name, numbers, locality), which is how
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
        'cartier' => 'cartier', 'cartierul' => 'cartier',
    ];

    /** Words that describe the kind of thing, not which one. */
    private const NOISE = [
        'strada', 'bulevardul', 'soseaua', 'calea', 'aleea', 'intrarea', 'piata', 'splaiul',
        'nr', 'judet', 'comuna', 'sat', 'municipiul', 'oras', 'localitatea', 'sector', 'bloc',
        'scara', 'etaj', 'ap', 'camera', 'romania', 'hala', 'pct', 'punct', 'punctul', 'lucru',
        'de', 'la', 'din', 'cu', 'in', 'fn', 'f', 'n', 'cf', 'cod', 'postal', 'zona', 'parter',
        'cartier', 'corp', 'corpul', 'cladire', 'cladirea', 'constructie', 'constructia', 'incinta',
        'incapere', 'incaperea', 'spatiu', 'spatiul', 'suprafata', 'supraf', 'unitatea', 'mp',
    ];

    /**
     * Words whose number says which part of the address it is, not which building: "sector 3" is
     * not house number 3. Their number is kept joined to the word ("sector3").
     */
    private const QUALIFIED = ['sector', 'etaj', 'ap', 'camera', 'bloc', 'scara', 'hala', 'lot', 'parcela'];

    public static function normalize(mixed $address): string
    {
        $folded = TextNormalizer::fold($address);

        if ($folded === '') {
            return '';
        }

        $folded = preg_replace(
            [
                // "B-dul" folds to two words.
                '/\bb dul\b/',
                // A road and its kilometre are one word however they are spaced: "DN 65", "DN65".
                '/\b(dn|dj|dc|km) (?=\d)/',
                // A letter after a house number belongs to it: "18 A" is "18a".
                '/\b(\d+) ([a-z])\b/',
                // "SECTORUL4" is "sectorul 4".
                '/\b(sectorul|sector|sect)(?=\d)/',
            ],
            ['bulevardul', '$1', '$1$2', '$1 '],
            $folded,
        ) ?? $folded;

        $tokens = array_map(
            fn (string $token): string => self::CANONICAL[$token] ?? $token,
            explode(' ', $folded),
        );

        return implode(' ', $tokens);
    }

    /**
     * The words that locate the building, deduplicated and sorted. The county's own name is
     * dropped because one clerk writes it and the next does not, and so are postal codes and
     * floor areas ("150 mp"), which one clerk adds and the next leaves out.
     */
    public static function fingerprint(mixed $address, ?string $countyCode = null): string
    {
        $countyWords = $countyCode !== null && ($name = RomanianCounties::name($countyCode)) !== null
            ? explode(' ', TextNormalizer::fold($name))
            : [];

        $words = explode(' ', preg_replace('/\b\d+ ?mp\b/', ' ', self::normalize($address)) ?? '');
        $tokens = [];

        for ($i = 0, $count = count($words); $i < $count; $i++) {
            $word = $words[$i];

            // "hala 1" and "hala nr. 1" are the same hall.
            $next = ($words[$i + 1] ?? '') === 'nr' ? $i + 2 : $i + 1;

            if (in_array($word, self::QUALIFIED, true) && preg_match('/^[a-z]?\d+[a-z]?$|^[a-z]$/', $words[$next] ?? '') === 1) {
                $tokens[] = $word.$words[$next];
                $i = $next;

                continue;
            }

            if ($word === ''
                || in_array($word, self::NOISE, true)
                || in_array($word, $countyWords, true)
                || (strlen($word) < 2 && ! ctype_digit($word))
                || preg_match('/^\d{6}$/', $word) === 1) {
                continue;
            }

            $tokens[] = $word;
        }

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

    /** @return list<string> house numbers ("12", "12a"), not "sector3" or "dn65" */
    private static function numbers(string $fingerprint): array
    {
        return array_values(array_filter(explode(' ', $fingerprint), fn (string $token): bool => ctype_digit($token[0] ?? '')));
    }
}
