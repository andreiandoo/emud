<?php

namespace App\Console\Commands;

use App\Enums\CatalogImportStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WatchCatalogSourceSync extends Command
{
    protected $signature = 'catalog:sources:watch
        {source? : Source code; defaults to whichever source ran most recently}
        {--interval=5 : Seconds between polls}
        {--wait=60 : Seconds to wait for a run to start before giving up}
        {--samples=3 : Records to show from the most recent batch}';

    protected $description = 'Follow a running catalog import and report progress, throughput and the records being staged.';

    /** @return list<string> */
    private function terminalStatuses(): array
    {
        return [
            CatalogImportStatus::Completed->value,
            CatalogImportStatus::CompletedWithErrors->value,
            CatalogImportStatus::Failed->value,
            CatalogImportStatus::Cancelled->value,
        ];
    }

    public function handle(): int
    {
        if (! Schema::hasTable('catalog_import_runs')) {
            $this->error('catalog_import_runs is missing; run migrations first.');

            return self::FAILURE;
        }

        $code = $this->argument('source');
        $interval = max(1, (int) $this->option('interval'));

        $run = $this->awaitRun($code, max(0, (int) $this->option('wait')), $interval);

        if ($run === null) {
            $this->warn($code === null
                ? 'No import run found. Queue one with catalog:sources:sync <CODE> --mode=catalog.'
                : "No import run found for {$code}. Queue one with catalog:sources:sync {$code} --mode=catalog.");
            $this->line('A run that never leaves "pending" means no worker covers the catalog-imports queue.');

            return self::FAILURE;
        }

        $this->line("Watching run #{$run->id}: {$run->code} {$run->mode}. Ctrl+C stops watching, not the import.");
        $this->newLine();

        // Throughput is measured against the first observed sample rather than started_at, so a
        // run that was already in progress when watching began still reports a meaningful rate.
        $baseCount = (int) $run->fetched_count + (int) $run->failed_count;
        $baseTime = microtime(true);

        while (true) {
            $run = $this->fetchRun($run->id);

            if ($run === null) {
                $this->error('The import run disappeared.');

                return self::FAILURE;
            }

            $this->line($this->progressLine($run, $baseCount, $baseTime));

            if (in_array((string) $run->status, $this->terminalStatuses(), true)) {
                return $this->summarise($run);
            }

            sleep($interval);
        }
    }

    private function awaitRun(?string $code, int $wait, int $interval): ?object
    {
        $deadline = microtime(true) + $wait;

        do {
            $run = $this->fetchLatestRun($code);

            // An already-finished run is only interesting if the caller asked for it explicitly;
            // when waiting for a freshly queued sync, keep polling until it actually starts.
            if ($run !== null && (! in_array((string) $run->status, $this->terminalStatuses(), true) || $wait === 0)) {
                return $run;
            }

            if (microtime(true) >= $deadline) {
                return $run;
            }

            sleep($interval);
        } while (true);
    }

    private function fetchLatestRun(?string $code): ?object
    {
        $query = $this->runQuery();

        if ($code !== null) {
            $query->where('catalog_sources.code', $code);
        }

        return $query->orderByDesc('catalog_import_runs.id')->first();
    }

    private function fetchRun(int $id): ?object
    {
        return $this->runQuery()->where('catalog_import_runs.id', $id)->first();
    }

    private function runQuery(): Builder
    {
        return DB::table('catalog_import_runs')
            ->leftJoin('catalog_sources', 'catalog_sources.id', '=', 'catalog_import_runs.catalog_source_id')
            ->select([
                'catalog_import_runs.id',
                'catalog_import_runs.catalog_source_id',
                'catalog_import_runs.mode',
                'catalog_import_runs.status',
                'catalog_import_runs.fetched_count',
                'catalog_import_runs.parsed_count',
                'catalog_import_runs.failed_count',
                'catalog_import_runs.error_message',
                'catalog_import_runs.started_at',
                'catalog_import_runs.finished_at',
                'catalog_sources.code',
            ]);
    }

    private function progressLine(object $run, int $baseCount, float $baseTime): string
    {
        $processed = (int) $run->fetched_count + (int) $run->failed_count;
        $elapsed = max(0.001, microtime(true) - $baseTime);
        $rate = ($processed - $baseCount) / $elapsed;

        $line = sprintf(
            '[%s] %-22s fetched=%s parsed=%s failed=%s  %s',
            $this->duration($run->started_at),
            (string) $run->status,
            number_format((int) $run->fetched_count),
            number_format((int) $run->parsed_count),
            number_format((int) $run->failed_count),
            $rate > 0 ? sprintf('%s/s', number_format($rate, 1)) : '—'
        );

        $sample = $this->latestRecords((int) $run->catalog_source_id, (int) $run->id);

        return $sample === '' ? $line : $line.'  last: '.$sample;
    }

    /**
     * Showing what is actually landing in staging distinguishes a healthy import from one that
     * is looping over the same page or ingesting rows of an unexpected record type.
     */
    private function latestRecords(int $sourceId, int $runId): string
    {
        if (! Schema::hasTable('catalog_source_records')) {
            return '';
        }

        $samples = (int) $this->option('samples');

        if ($samples < 1) {
            return '';
        }

        $rows = DB::table('catalog_source_records')
            ->where('catalog_source_id', $sourceId)
            ->where('catalog_import_run_id', $runId)
            ->orderByDesc('id')
            ->limit($samples)
            ->get(['record_type', 'external_id']);

        return $rows
            ->map(fn (object $row) => $row->record_type.':'.$row->external_id)
            ->implode(', ');
    }

    private function summarise(object $run): int
    {
        $this->newLine();

        $staged = Schema::hasTable('catalog_source_records')
            ? DB::table('catalog_source_records')->where('catalog_import_run_id', $run->id)->count()
            : 0;

        $this->table(['Field', 'Value'], [
            ['source', (string) ($run->code ?? 'unknown')],
            ['mode', (string) $run->mode],
            ['status', (string) $run->status],
            ['fetched', number_format((int) $run->fetched_count)],
            ['parsed', number_format((int) $run->parsed_count)],
            ['failed', number_format((int) $run->failed_count)],
            ['staged this run', number_format($staged)],
            ['duration', $this->duration($run->started_at, $run->finished_at)],
        ]);

        if ($run->error_message !== null && $run->error_message !== '') {
            $this->error($run->error_message);
        }

        if ((string) $run->status === CatalogImportStatus::Failed->value) {
            return self::FAILURE;
        }

        $this->info('Canonicalization runs as a separate job on the catalog-canonicalization queue.');
        $this->line('Follow it with: php artisan catalog:system:check');

        return self::SUCCESS;
    }

    private function duration(mixed $from, mixed $to = null): string
    {
        if ($from === null) {
            return '--:--:--';
        }

        $seconds = (int) Carbon::parse((string) $from)->diffInSeconds(
            $to === null ? Carbon::now() : Carbon::parse((string) $to),
            absolute: true
        );

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
