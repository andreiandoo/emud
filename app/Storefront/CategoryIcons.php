<?php

namespace App\Storefront;

/**
 * The icons a category may carry in the mega menu.
 *
 * The drawings live in the storefront icon component; this is only the list an operator may
 * choose from, so the back office offers a fixed set rather than a free-text field where a
 * typo silently produces a blank square. CategoryIconsTest keeps the two in step.
 */
final class CategoryIcons
{
    /** @var array<string, string> */
    public const OPTIONS = [
        'suspension' => 'Suspensie',
        'shock' => 'Amortizoare',
        'wheel' => 'Jante',
        'tyre' => 'Anvelope',
        'brake' => 'Frâne',
        'engine' => 'Motor',
        'exhaust' => 'Evacuare',
        'cooling' => 'Răcire',
        'transmission' => 'Transmisie',
        'electrical' => 'Electrice',
        'light' => 'Iluminat',
        'body' => 'Caroserie',
        'interior' => 'Interior',
        'winch' => 'Trolii',
        'roof-rack' => 'Portbagaj & suporturi',
        'oil' => 'Uleiuri & fluide',
        'filter' => 'Filtre',
        'tool' => 'Scule',
        'offroad' => 'Off-road',
        'part' => 'Generic',
    ];

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::OPTIONS);
    }

    public static function resolve(?string $key): string
    {
        return self::exists($key) ? (string) $key : 'part';
    }
}
