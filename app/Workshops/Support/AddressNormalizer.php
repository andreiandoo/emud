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
        'de', 'la', 'din', 'cu', 'si', 'in', 'fn', 'f', 'n', 'cf', 'cod', 'postal', 'zona', 'parter',
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
                // A road and its kilometre are one word however they are spaced: "DN 65 B", "DN65B".
                '/\b(dn|dj|dc|km) ?(\d+)(?: ?([a-z])\b)?/',
                // "NR1E" is "nr 1e".
                '/\bnr(?=\d)/',
                // A letter or "bis" after a house number belongs to it ("18 A" is "18a"), unless the
                // letter starts what follows: in "nr. 31, T.29-P.149" the T is a plot, not 31T.
                '/\b(\d+) (bis|[a-z])\b(?! ?\d)/',
                // "SECTORUL4" is "sectorul 4".
                '/\b(sectorul|sector|sect)(?=\d)/',
            ],
            ['bulevardul', '$1$2$3', 'nr ', '$1$2', '$1 '],
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

        $numbersLeft = self::houseNumbers($a, $countyCode);
        $numbersRight = self::houseNumbers($b, $countyCode);

        if ($numbersLeft !== [] && $numbersRight !== [] && array_intersect($numbersLeft, $numbersRight) === []) {
            return 0.0;
        }

        ['matched' => $matched, 'left' => $leftCount, 'right' => $rightCount] = TokenSimilarity::overlap($left, $right);
        $jaccard = TokenSimilarity::compare($left, $right);
        $smaller = min($leftCount, $rightCount);
        $contained = $smaller >= 2 ? round(0.95 * $matched / $smaller, 4) : 0.0;

        return max($jaccard, $contained);
    }

    /** Both addresses give a house number and none is shared: two buildings. */
    public static function houseNumbersDisagree(mixed $a, mixed $b, ?string $countyCode = null): bool
    {
        $left = self::houseNumbers($a, $countyCode);
        $right = self::houseNumbers($b, $countyCode);

        return $left !== [] && $right !== [] && array_intersect($left, $right) === [];
    }

    /** Both addresses give a house number and share one. */
    public static function houseNumbersAgree(mixed $a, mixed $b, ?string $countyCode = null): bool
    {
        $left = self::houseNumbers($a, $countyCode);
        $right = self::houseNumbers($b, $countyCode);

        return $left !== [] && $right !== [] && array_intersect($left, $right) !== [];
    }

    /**
     * Whether two addresses name the same street: true when they share a street word, false when
     * both name a street and share none, null when either names none. A word spelt with â or with î,
     * or declined ("București", "Bucureștilor"), is the same word.
     *
     * @param  list<string|null>  $localities  place names both sides share, which tell nothing apart
     */
    public static function sameStreet(mixed $a, mixed $b, ?string $countyCode = null, array $localities = []): ?bool
    {
        $left = self::streetWords($a, $countyCode, $localities);
        $right = self::streetWords($b, $countyCode, $localities);

        if ($left === [] || $right === []) {
            return null;
        }

        foreach ($left as $x) {
            foreach ($right as $y) {
                if (self::sameWord($x, $y)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The house numbers an address gives: what follows "nr" when it says "nr", since a number in
     * the street's own name ("Str. 1 Decembrie 1918", "Str. Euro 85") is not one; else every number.
     *
     * @return list<string>
     */
    private static function houseNumbers(mixed $address, ?string $countyCode): array
    {
        $words = explode(' ', preg_replace('/\b\d+ ?mp\b/', ' ', self::normalize($address)) ?? '');
        $numbers = [];

        foreach ($words as $i => $word) {
            // "CF nr. 63580" and "nr. cadastral 91774" are land-register numbers.
            if ($word !== 'nr' || in_array($words[$i - 1] ?? '', ['cf', 'cad', 'cadastral'], true)) {
                continue;
            }

            for ($j = $i + 1; isset($words[$j]) && ctype_digit($words[$j][0] ?? ''); $j++) {
                $numbers[] = $words[$j];
            }
        }

        return $numbers !== [] ? array_values(array_unique($numbers)) : self::numbers(self::fingerprint($address, $countyCode));
    }

    /** @return list<string> the words that name the street or road: no places, numbers or kinds of thing */
    private static function streetWords(mixed $address, ?string $countyCode, array $localities): array
    {
        $places = [];

        foreach ([$countyCode !== null ? RomanianCounties::name($countyCode) : null, ...$localities] as $place) {
            foreach (explode(' ', TextNormalizer::fold($place)) as $word) {
                $places[$word] = true;
            }
        }

        $words = explode(' ', self::normalize($address));
        $street = [];

        foreach ($words as $i => $word) {
            $road = preg_match('/^(dn|dj|dc)\d/', $word) === 1;

            if (strlen($word) < 2
                || isset($places[$word])
                || in_array($word, self::NOISE, true)
                || in_array($word, self::QUALIFIED, true)
                || in_array($words[$i - 1] ?? '', self::QUALIFIED, true)
                || (! $road && preg_match('/\d/', $word) === 1)) {
                continue;
            }

            $street[] = $word;
        }

        return array_values(array_unique($street));
    }

    private static function sameWord(string $x, string $y): bool
    {
        if (TokenSimilarity::sameToken($x, $y)) {
            return true;
        }

        $shorter = min(strlen($x), strlen($y));
        $prefix = 0;

        while ($prefix < $shorter && $x[$prefix] === $y[$prefix]) {
            $prefix++;
        }

        // "dambovita" and "dambovitei": one street, declined.
        return $prefix >= 6 && $prefix >= 0.7 * $shorter;
    }

    /** @return list<string> house numbers ("12", "12a"), not "sector3" or "dn65" */
    private static function numbers(string $fingerprint): array
    {
        return array_values(array_filter(explode(' ', $fingerprint), fn (string $token): bool => ctype_digit($token[0] ?? '')));
    }
}
