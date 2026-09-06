<?php

namespace App\Catalog\Vehicles\Vin;

class VpicResultFlattener
{
    /** @param iterable<array<string, mixed>|object> $rows */
    public function flatten(iterable $rows): array
    {
        $decoded = [];

        foreach ($rows as $row) {
            $values = is_object($row) ? get_object_vars($row) : $row;
            $values = array_change_key_case($values, CASE_LOWER);
            $variable = trim((string) ($values['variable'] ?? $values['variablename'] ?? ''));
            $value = $values['value'] ?? null;

            if ($variable === '' || $value === null || $value === '') {
                continue;
            }

            if (! array_key_exists($variable, $decoded)) {
                $decoded[$variable] = $value;

                continue;
            }

            if ($decoded[$variable] === $value) {
                continue;
            }

            $existing = is_array($decoded[$variable]) ? $decoded[$variable] : [$decoded[$variable]];
            if (! in_array($value, $existing, true)) {
                $existing[] = $value;
            }
            $decoded[$variable] = $existing;
        }

        return $decoded;
    }
}
