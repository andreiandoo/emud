<?php

namespace App\Suppliers\Parsing;

use RuntimeException;

class StructuredSupplierFeedParser
{
    /** @param resource $stream
     *  @return iterable<array<string, mixed>>
     */
    public function rows($stream, string $format, array $settings = []): iterable
    {
        if (! is_resource($stream)) {
            throw new RuntimeException('Supplier feed parser requires a readable stream.');
        }

        yield from match (strtolower($format)) {
            'csv', 'tsv' => $this->csvRows($stream, strtolower($format), $settings),
            'xml' => $this->xmlRows($stream, $settings),
            'json' => $this->jsonRows($stream, $settings),
            default => throw new RuntimeException("Unsupported supplier feed format: {$format}."),
        };
    }

    /** @param resource $stream
     *  @return iterable<array<string, mixed>>
     */
    private function csvRows($stream, string $format, array $settings): iterable
    {
        $delimiter = (string) ($settings['delimiter'] ?? ($format === 'tsv' ? "\t" : ','));
        $enclosure = (string) ($settings['enclosure'] ?? '"');
        $escape = (string) ($settings['escape'] ?? '\\');
        $skipRows = max(0, (int) ($settings['skip_rows'] ?? 0));

        for ($index = 0; $index < $skipRows; $index++) {
            if (fgets($stream) === false) {
                return;
            }
        }

        $configuredHeaders = $settings['headers'] ?? null;
        if (is_array($configuredHeaders) && $configuredHeaders !== []) {
            $headers = array_values(array_map(static fn (mixed $header): string => trim((string) $header), $configuredHeaders));
        } else {
            $headers = fgetcsv($stream, separator: $delimiter, enclosure: $enclosure, escape: $escape) ?: [];
            if ($headers !== []) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
                $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
            }
        }

        if ($headers === []) {
            return;
        }

        while (($values = fgetcsv($stream, separator: $delimiter, enclosure: $enclosure, escape: $escape)) !== false) {
            if ($values === [null] || count($values) !== count($headers)) {
                continue;
            }

            $row = array_combine($headers, $values);
            if (is_array($row)) {
                yield $row;
            }
        }
    }

    /** @param resource $stream
     *  @return iterable<array<string, mixed>>
     */
    private function xmlRows($stream, array $settings): iterable
    {
        $contents = stream_get_contents($stream);
        if ($contents === false || trim($contents) === '') {
            return;
        }

        $xml = simplexml_load_string($contents, options: LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('Supplier feed XML is invalid.');
        }

        $nodes = $xml->xpath((string) ($settings['items_xpath'] ?? '//product')) ?: [];
        foreach ($nodes as $node) {
            $row = json_decode(json_encode($node, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            if (is_array($row)) {
                yield $row;
            }
        }
    }

    /** @param resource $stream
     *  @return iterable<array<string, mixed>>
     */
    private function jsonRows($stream, array $settings): iterable
    {
        $contents = stream_get_contents($stream);
        if ($contents === false || trim($contents) === '') {
            return;
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $rows = data_get($payload, (string) ($settings['items_path'] ?? 'data'), $payload);
        if (! is_iterable($rows)) {
            throw new RuntimeException('Supplier feed JSON items path is not iterable.');
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                yield $row;
            } elseif (is_object($row)) {
                yield (array) $row;
            }
        }
    }
}
