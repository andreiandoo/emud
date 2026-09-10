<?php

namespace App\Workshops\Sources\Osm;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Cuts the workshops out of the Romania extract with osmium-tool, rather than parsing the PBF in
 * PHP: `osmium tags-filter` keeps the objects with a workshop tag (and the nodes their outlines
 * need), and `osmium export` writes them as GeoJSON Text Sequences, one feature per line, with
 * every tag as a property.
 *
 * osmium must be installed on the machine that runs the import (apt install osmium-tool); see
 * docs/workshops/operations.md.
 */
class OsmiumExtractor
{
    public function available(): bool
    {
        try {
            return Process::timeout(20)->run([$this->binary(), '--version'])->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function extract(string $pbf): string
    {
        $directory = dirname($pbf);
        $filtered = $directory.'/workshops.osm.pbf';
        $export = $directory.'/workshops.geojsonseq';

        $this->run([$this->binary(), 'tags-filter', $pbf, ...array_values((array) config('workshops.osm.tag_filters')), '-o', $filtered, '--overwrite']);
        $this->run([$this->binary(), 'export', $filtered, '-f', 'geojsonseq', '-o', $export, '--overwrite', '--add-unique-id=type_id', '--attributes=type,id']);

        return $export;
    }

    private function run(array $command): void
    {
        $result = Process::timeout((int) config('workshops.osm.request_timeout'))->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('osmium failed: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function binary(): string
    {
        return (string) config('workshops.osm.osmium_binary', 'osmium');
    }
}
