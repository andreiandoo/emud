<?php

namespace App\Workshops\Ingestion;

use InvalidArgumentException;

/**
 * Every source the registry can hold data from, and how its row is first created.
 *
 * Nothing is cleared for public output by default. RAR's registry is public but its reuse terms
 * are not stated; OpenStreetMap is ODbL and publishable with attribution, which is a decision to
 * take deliberately rather than by default. The flag lives on the row, where an operator can flip it.
 */
class DataSourceCatalog
{
    public const ONRC = 'onrc';

    public const OSM = 'osm';

    public const WEBSITE = 'website';

    public const WEB_SEARCH = 'web_search';

    public const MANUAL = 'manual';

    /** @return array{name: string, type: string, configuration?: array<string, mixed>} */
    public static function definition(string $key): array
    {
        $section = self::rarSectionFor($key);

        if ($section !== null) {
            return [
                'name' => (string) config("workshops.rar.sections.{$section}.name"),
                'type' => 'registry',
                'configuration' => ['section' => $section, 'authority' => 'RAR'],
            ];
        }

        return match ($key) {
            self::ONRC => ['name' => 'ONRC – firme înregistrate (data.gov.ro)', 'type' => 'open_data', 'configuration' => ['license' => 'Licență deschisă data.gov.ro (de verificat per set de date)']],
            self::OSM => ['name' => 'OpenStreetMap – extras România (Geofabrik)', 'type' => 'poi', 'configuration' => ['license' => 'ODbL 1.0', 'attribution' => '© OpenStreetMap contributors']],
            self::WEBSITE => ['name' => 'Site-urile proprii ale atelierelor', 'type' => 'web'],
            self::WEB_SEARCH => ['name' => 'Căutare web (API configurat)', 'type' => 'web'],
            self::MANUAL => ['name' => 'Introdus manual', 'type' => 'manual'],
            default => throw new InvalidArgumentException("Unknown workshop data source [{$key}]."),
        };
    }

    /** @return list<string> */
    public static function rarSections(): array
    {
        return array_keys((array) config('workshops.rar.sections'));
    }

    public static function rarKey(string $section): string
    {
        $key = config('workshops.rar.sections.'.strtoupper($section).'.source');

        if (! is_string($key)) {
            throw new InvalidArgumentException("Unknown RAR registry section [{$section}].");
        }

        return $key;
    }

    public static function rarSectionFor(string $key): ?string
    {
        foreach ((array) config('workshops.rar.sections') as $section => $definition) {
            if (($definition['source'] ?? null) === $key) {
                return $section;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function rarKeys(): array
    {
        return array_map(self::rarKey(...), self::rarSections());
    }
}
