<?php

namespace App\Workshops\Support;

/**
 * Word-level similarity for folded strings, from 0 (nothing shared) to 1 (the same words).
 *
 * Two words count as the same when they are identical, or when both are long enough and differ
 * by a single letter. That absorbs the î/â spelling split ("Gîrcina" and "Gârcina"), a dropped
 * diacritic and ordinary typos, without letting "pacea" match "parcul". Numbers must match
 * exactly: "nr 90" and "nr 9" are different buildings.
 */
class TokenSimilarity
{
    /** Words that say nothing about which business or place this is. */
    private const NOISE = [
        'si', 'de', 'la', 'din', 'cu', 'pe', 'the', 'and',
    ];

    public static function compare(string $a, string $b): float
    {
        ['matched' => $matched, 'left' => $left, 'right' => $right] = self::overlap($a, $b);
        $union = $left + $right - $matched;

        return $union === 0 ? 0.0 : round($matched / $union, 4);
    }

    /** @return array{matched: int, left: int, right: int} words in common, and how many each side has */
    public static function overlap(string $a, string $b): array
    {
        $left = self::tokens($a);
        $right = self::tokens($b);
        $matched = 0;
        $unmatched = $right;

        foreach ($left as $token) {
            foreach ($unmatched as $index => $candidate) {
                if (self::sameToken($token, $candidate)) {
                    $matched++;
                    unset($unmatched[$index]);

                    continue 2;
                }
            }
        }

        return ['matched' => $matched, 'left' => count($left), 'right' => count($right)];
    }

    /** @return list<string> */
    public static function tokens(string $folded): array
    {
        $tokens = array_filter(
            explode(' ', $folded),
            fn (string $token): bool => $token !== '' && ! in_array($token, self::NOISE, true),
        );

        return array_values(array_unique($tokens));
    }

    public static function sameToken(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (preg_match('/\d/', $a.$b) === 1) {
            return false;
        }

        return strlen($a) >= 5 && strlen($b) >= 5 && levenshtein($a, $b) <= 1;
    }
}
