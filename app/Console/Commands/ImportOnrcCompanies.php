<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOnrcRelease;
use App\Workshops\Sources\Onrc\OnrcDatasetLocator;
use App\Workshops\Sources\Onrc\OnrcDownloader;
use App\Workshops\Sources\Onrc\OnrcImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportOnrcCompanies extends Command
{
    protected $signature = 'workshops:onrc:import
        {--release= : A specific data.gov.ro package name}
        {--keep-files : Keep the downloaded files after the import}
        {--limit= : Store at most this many companies (trial runs; nothing is retired)}
        {--force : Import the release even if it was already imported}
        {--sync : Run here instead of on the queue}';

    protected $description = 'Import legal identity, status and CAEN activities from the newest ONRC release.';

    public function handle(OnrcDatasetLocator $locator, OnrcDownloader $downloader, OnrcImporter $importer): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        if (! $this->option('sync')) {
            ProcessOnrcRelease::dispatch($this->option('release'), (bool) $this->option('keep-files'), $limit, (bool) $this->option('force'));
            $this->info('Queued the ONRC import on the "workshops" queue. Follow it with: php artisan workshops:status');

            return self::SUCCESS;
        }

        try {
            $run = ProcessOnrcRelease::run($locator, $downloader, $importer, $this->option('release'), (bool) $this->option('keep-files'), $limit, fn (string $message) => $this->line('  '.$message), (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($run === null) {
            return self::SUCCESS;
        }

        $counts = $run->metadata['counts'] ?? [];
        $this->info(sprintf(
            'ONRC %s: %s automotive companies in the register, %s kept, %s new, %s changed, %s unchanged, %s failed, %s retired.',
            $run->scope['release'] ?? '',
            number_format($counts['automotive'] ?? 0), number_format($counts['kept'] ?? 0), number_format($counts['created'] ?? 0),
            number_format($counts['updated'] ?? 0), number_format($counts['unchanged'] ?? 0), number_format($counts['failed'] ?? 0), number_format($counts['retired'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
