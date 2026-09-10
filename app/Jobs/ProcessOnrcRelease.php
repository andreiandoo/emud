<?php

namespace App\Jobs;

use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Onrc\OnrcDatasetLocator;
use App\Workshops\Sources\Onrc\OnrcDownloader;
use App\Workshops\Sources\Onrc\OnrcImporter;
use App\Workshops\Sources\Onrc\OnrcRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Downloads (if needed) and imports one ONRC release. A few passes over 1.2 GB of files take
 * minutes, not hours, so one job does it, under a lock so two releases never interleave.
 */
class ProcessOnrcRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public readonly ?string $releaseKey = null, public readonly bool $keepFiles = false, public readonly ?int $limit = null, public readonly bool $force = false)
    {
        $this->onQueue('workshops');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('workshops:onrc'))->expireAfter(7300)->dontRelease()];
    }

    public function handle(OnrcDatasetLocator $locator, OnrcDownloader $downloader, OnrcImporter $importer): void
    {
        self::run($locator, $downloader, $importer, $this->releaseKey, $this->keepFiles, $this->limit, force: $this->force);
    }

    /**
     * Shared by the job and the command's --sync mode. Returns null when the newest release was
     * already imported: the monthly schedule then costs one API call, not 1.2 GB.
     */
    public static function run(OnrcDatasetLocator $locator, OnrcDownloader $downloader, OnrcImporter $importer, ?string $releaseKey, bool $keepFiles, ?int $limit, ?callable $progress = null, bool $force = false): ?WorkshopImportRun
    {
        $source = WorkshopDataSource::forKey(DataSourceCatalog::ONRC);
        $release = $locator->latest($releaseKey);

        if (! $force && $limit === null && $source->stateValue('release.key') === $release->key) {
            $progress && $progress("{$release->key} was already imported; nothing to do (use --force to import it again)");

            return null;
        }

        $run = WorkshopImportRun::start($source, ['release' => $release->key, 'limit' => $limit]);

        try {
            $files = $downloader->download($release, progress: $progress);
            $run->updateMetadata(fn (array $metadata): array => $metadata + [
                'release' => $release->toArray(),
                'files' => collect($files)->map(fn (array $file): array => ['sha256' => $file['sha256'], 'size' => $file['size'], 'resource_id' => $file['resource_id'], 'url' => $file['url']])->all(),
                'downloaded_at' => now()->toIso8601String(),
            ]);

            $counts = $importer->import($release, $files, $run, $progress, $limit);
            $run->updateMetadata(fn (array $metadata): array => $metadata + ['counts' => $counts]);
            $source->putState('release', ['key' => $release->key, 'title' => $release->title, 'imported_at' => now()->toIso8601String(), 'files' => $run->fresh()->metadata['files'] ?? []]);
            $run->finish();

            if (! ($keepFiles || config('workshops.onrc.keep_files'))) {
                $downloader->forget($release);
            }
        } catch (Throwable $exception) {
            $run->fail($exception);

            throw $exception;
        }

        return $run->fresh();
    }

    public static function release(OnrcDatasetLocator $locator, ?string $key): OnrcRelease
    {
        return $locator->latest($key);
    }
}
