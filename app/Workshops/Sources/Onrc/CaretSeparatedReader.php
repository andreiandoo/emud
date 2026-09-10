<?php

namespace App\Workshops\Sources\Onrc;

use Generator;
use RuntimeException;

/**
 * Streams ONRC's "^"-separated files (UTF-8 with a byte-order mark, CRLF) one row at a time, as
 * arrays keyed by the header. Nothing is loaded whole: the company file is ~700 MB.
 *
 * Deliberately not fgetcsv: ONRC does not quote fields, and a company name that starts with a
 * quote ("HOPE SPED" SRL) would be mangled by a CSV parser. A row cut in two by a line break
 * inside a field is joined back until it has every column.
 */
class CaretSeparatedReader
{
    private const MAX_CONTINUATION_BYTES = 20_000;

    /** @return Generator<int, array<string, string>> */
    public static function rows(string $path): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        try {
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                return;
            }

            $header = array_map(fn (string $column): string => strtoupper(trim($column)), explode('^', self::strip($headerLine)));
            $columns = count($header);
            $buffer = null;
            $line = 0;

            while (($raw = fgets($handle)) !== false) {
                $raw = rtrim($raw, "\r\n");
                $buffer = $buffer === null ? $raw : $buffer.' '.$raw;
                $parts = explode('^', $buffer);

                if (count($parts) < $columns) {
                    // An unfinished row; a runaway one is dropped rather than swallowing the file.
                    if (strlen($buffer) > self::MAX_CONTINUATION_BYTES) {
                        $buffer = null;
                    }

                    continue;
                }

                $buffer = null;

                if (count($parts) > $columns) {
                    $parts = [...array_slice($parts, 0, $columns - 1), implode('^', array_slice($parts, $columns - 1))];
                }

                if (trim(implode('', $parts)) === '') {
                    continue;
                }

                yield $line++ => array_combine($header, array_map('trim', $parts));
            }
        } finally {
            fclose($handle);
        }
    }

    private static function strip(string $line): string
    {
        return rtrim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line, "\r\n");
    }
}
