<?php

namespace App\Console\Commands;

use App\Jobs\FetchRarCounty;
use App\Models\WorkshopImportRun;
use App\Workshops\Data\SourcePartition;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Rar\RarImporter;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Throwable;

class ImportRarWorkshops extends Command
{
    protected $signature = 'workshops:rar:import
        {--section=SERVICE : SERVICE, ITP, GPL, TLV, B4, or "all"}
        {--county= : One county, by code (BV) or as the registry names it (Brasov)}
        {--limit= : At most this many records per county; a limited run retires nothing}
        {--resume : Continue the latest unfinished run instead of starting a new one}
        {--force : Reparse every record, not only the ones whose content changed}
        {--dry-run : Fetch and count only; store nothing}
        {--sync : Run here, county after county, instead of queueing one job per county}';

    protected $description = 'Import workshops from the public RAR registry, county by county.';

    public function handle(RarImporter $importer): int
    {
        if (! config('workshops.rar.enabled')) {
            $this->warn('RAR import is disabled (RAR_IMPORT_ENABLED=false).');

            return self::FAILURE;
        }

        $section = strtoupper((string) $this->option('section'));
        $sections = $section === 'ALL' ? DataSourceCatalog::rarSections() : [$section];

        if (array_diff($sections, DataSourceCatalog::rarSections()) !== []) {
            $this->error("Unknown section [{$section}]. Use one of: ".implode(', ', DataSourceCatalog::rarSections()).', all.');

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $dryRun = (bool) $this->option('dry-run');
        $inline = $dryRun || (bool) $this->option('sync');
        $status = self::SUCCESS;

        foreach ($sections as $current) {
            try {
                [$run, $partitions] = $importer->plan($current, $this->option('county'), (bool) $this->option('resume'), [
                    'limit' => $limit,
                    'force' => (bool) $this->option('force'),
                    'dry_run' => $dryRun,
                ]);
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            } catch (Throwable $exception) {
                $this->error("{$current}: could not plan the import: ".$exception->getMessage());
                $status = self::FAILURE;

                continue;
            }

            if ($partitions === []) {
                $run->finish();
                $this->info("{$current}: nothing left to import in run {$run->uuid}.");

                continue;
            }

            $inline
                ? $this->runInline($importer, $run, $partitions, $limit, $dryRun)
                : $this->queue($run, $partitions, $limit);
        }

        return $status;
    }

    /** @param list<SourcePartition> $partitions */
    private function runInline(RarImporter $importer, WorkshopImportRun $run, array $partitions, ?int $limit, bool $dryRun): void
    {
        $section = $run->scope['section'];
        $this->info("{$section}: ".count($partitions)." counties, run {$run->uuid}".($dryRun ? ' (dry run)' : ''));

        foreach ($partitions as $partition) {
            try {
                $counts = $importer->importPartition($run, $partition, (bool) $this->option('force'), $limit, $dryRun);
                $this->line(sprintf(
                    '  %-18s %5d seen · %4d new · %4d changed · %5d unchanged · %3d repeated · %3d failed · %3d retired',
                    $partition->label, $counts['seen'], $counts['created'], $counts['updated'], $counts['unchanged'], $counts['duplicate'], $counts['failed'], $counts['retired'],
                ));
            } catch (Throwable $exception) {
                $this->error("  {$partition->label}: ".$exception->getMessage().' (continue later with --resume)');
            }
        }

        $run->finish();
        $run->refresh();
        $this->info("{$section}: {$run->status->label()} — {$run->discovered_count} seen, {$run->created_count} new, {$run->updated_count} changed, {$run->unchanged_count} unchanged, {$run->failed_count} failed, {$run->retired_count} retired.");
    }

    /** @param list<SourcePartition> $partitions */
    private function queue(WorkshopImportRun $run, array $partitions, ?int $limit): void
    {
        $runId = $run->id;
        $jobs = array_map(fn (SourcePartition $partition): FetchRarCounty => new FetchRarCounty($runId, $partition->toArray(), (bool) $this->option('force'), $limit), $partitions);

        Bus::batch($jobs)
            ->name("RAR {$run->scope['section']} import #{$runId}")
            ->onQueue('workshops')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($runId): void {
                WorkshopImportRun::query()->find($runId)?->finish();
            })
            ->dispatch();

        $this->info("{$run->scope['section']}: queued ".count($jobs)." counties on the \"workshops\" queue (run {$run->uuid}). Follow it with: php artisan workshops:status");
    }
}
