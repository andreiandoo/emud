<?php

namespace App\Workshops\Support;

/**
 * Company names reduced to what identifies the business: "S.C. AB AUTOBELLA SERVICE S.R.L." and
 * "AB AUTOBELLA SERVICE SRL" both become "ab autobella service".
 *
 * Only for matching. The legal name is always stored as the source wrote it.
 */
class CompanyNameNormalizer
{
    /**
     * Folded spellings of each Romanian legal form. The longer form comes first wherever one is a
     * prefix of another, so "SRL-D" is recognised before "SRL" and "PFA" before "PF".
     */
    private const LEGAL_FORMS = [
        'SRL-D' => ['s r l d', 'srl d', 'srld'],
        'SRL' => ['s r l', 'srl'],
        'SCS' => ['s c s', 'scs'],
        'SNC' => ['s n c', 'snc'],
        'SCA' => ['s c a', 'sca'],
        'SA' => ['s a', 'sa'],
        'PFA' => ['persoana fizica autorizata', 'p f a', 'pfa'],
        'II' => ['intreprindere individuala', 'intreprinderea individuala', 'i i', 'ii'],
        'IF' => ['intreprindere familiala', 'intreprinderea familiala', 'i f', 'if'],
        'PF' => ['persoana fizica', 'p f', 'pf'],
        'COOP' => ['societate cooperativa', 'coop'],
    ];

    public static function normalize(mixed $name): string
    {
        $folded = TextNormalizer::fold($name);

        if ($folded === '') {
            return '';
        }

        $core = preg_replace('/^(?:s c|sc|societatea comerciala|societatea)\s+/', '', $folded) ?? $folded;

        // "X S.R.L. S.A." is not a real name, but "X PFA SRL" style stacking does occur, so the
        // suffixes are stripped until none is left.
        do {
            $before = $core;

            foreach (self::LEGAL_FORMS as $spellings) {
                foreach ($spellings as $spelling) {
                    $core = preg_replace('/\s+'.preg_quote($spelling, '/').'$/', '', $core) ?? $core;
                }
            }
        } while ($core !== $before && $core !== '');

        $core = trim($core);

        return $core === '' ? $folded : $core;
    }

    public static function legalForm(mixed $name): ?string
    {
        $folded = TextNormalizer::fold($name);

        foreach (self::LEGAL_FORMS as $form => $spellings) {
            foreach ($spellings as $spelling) {
                if (preg_match('/(?:^|\s)'.preg_quote($spelling, '/').'$/', $folded) === 1) {
                    return $form;
                }
            }
        }

        return null;
    }

    /**
     * How alike two names are once legal forms and punctuation are gone, from 0 to 1. Word order
     * is ignored ("SERVICE AUTO ION" against "ION SERVICE AUTO"); spelling is compared per word.
     */
    public static function similarity(mixed $a, mixed $b): float
    {
        return TokenSimilarity::compare(self::normalize($a), self::normalize($b));
    }
}
