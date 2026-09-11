<?php

namespace App\Catalog\Vehicles\Vin;

/**
 * The model a VIN names where vPIC does not say.
 *
 * vPIC is the United States' database: for a car built for Europe it often knows the maker and
 * the model year and nothing else. Some makers spell the model line out in a fixed position of
 * the VIN, and for those the model can be read straight off it. Land Rover puts it fourth:
 * SALC… is a Discovery Sport, SALV… an Evoque.
 *
 * Only lines known for certain are listed. A guess here would select the wrong car, which is
 * worse than asking the customer to pick it.
 */
final class VinModelHints
{
    /** World manufacturer code => [position of the model letter, letter => catalogue model names, most specific first]. */
    private const LINES = [
        'SAL' => [4, [
            'C' => ['Discovery Sport'],
            'E' => ['Defender'],
            'F' => ['Freelander 2', 'Freelander'],
            'G' => ['Range Rover'],
            'R' => ['Discovery'],
            'V' => ['Range Rover Evoque', 'Evoque'],
            'W' => ['Range Rover Sport'],
            'Y' => ['Range Rover Velar', 'Velar'],
        ]],
    ];

    /** @return list<string> */
    public static function modelNames(string $vin): array
    {
        $vin = strtoupper(trim($vin));

        foreach (self::LINES as $wmi => [$position, $models]) {
            if (strlen($vin) >= $position && str_starts_with($vin, $wmi)) {
                return $models[$vin[$position - 1]] ?? [];
            }
        }

        return [];
    }
}
