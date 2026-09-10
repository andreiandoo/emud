<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshWorkshops extends Command
{
    protected $signature = 'workshops:refresh
        {--source=* : rar, onrc, osm (repeatable; default: all three)}
        {--county= : RAR only: one county}
        {--force : Reparse unchanged RAR records; import ONRC and OSM even if unchanged}
        {--dry-run : RAR only: fetch and count, store nothing}
        {--deduplicate : Run workshops:deduplicate afterwards (only meaningful with --sync)}
        {--sync : Run everything here instead of queueing it}';

    protected $description = 'The normal incremental refresh: RAR registry, then ONRC and OpenStreetMap. Never crawls websites.';

    public function handle(): int
    {
        $sources = array_map('strtolower', (array) $this->option('source')) ?: ['rar', 'onrc', 'osm'];
        $unknown = array_diff($sources, ['rar', 'onrc', 'osm']);

        if ($unknown !== []) {
            $this->error('Unknown source: '.implode(', ', $unknown).'. Use rar, onrc or osm.');

            return self::FAILURE;
        }

        $status = self::SUCCESS;
        $sync = (bool) $this->option('sync');

        if (in_array('rar', $sources, true)) {
            $status = max($status, $this->call('workshops:rar:import', array_filter([
                '--section' => 'all',
                '--county' => $this->option('county'),
                '--force' => (bool) $this->option('force'),
                '--dry-run' => (bool) $this->option('dry-run'),
                '--sync' => $sync,
            ], fn (mixed $value): bool => $value !== null && $value !== false)));
        }

        if ($this->option('dry-run')) {
            $this->line('Dry run: ONRC and OpenStreetMap are left untouched.');

            return $status;
        }

        foreach (['onrc' => 'workshops:onrc:import', 'osm' => 'workshops:osm:import'] as $source => $command) {
            if (in_array($source, $sources, true)) {
                $status = max($status, $this->call($command, array_filter(['--force' => (bool) $this->option('force'), '--sync' => $sync])));
            }
        }

        if ($sync && $this->option('deduplicate')) {
            $status = max($status, $this->call('workshops:deduplicate'));
        }

        return $status;
    }
}
