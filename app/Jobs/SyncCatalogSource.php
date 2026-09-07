<?php

namespace App\Jobs;

use App\Catalog\Sources\CatalogSourceIngestor;
use App\Catalog\Sources\CatalogSourceRegistry;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
use App\Catalog\Sources\Contracts\ResumableCatalogSourceConnector;
use App\Enums\CatalogImportStatus;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SyncCatalogSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    /**
     * A feed larger than one timeout window now resumes instead of restarting, so attempts are
     * budget rather than retries of the same work: the EEA cars dataset needs roughly three
     * windows. Genuine errors are still capped by maxExceptions so a broken connector fails
     * quickly instead of consuming the whole budget.
     */
    public int $tries = 6;

    public int $maxExceptions = 3;

    /**
     * Longer than the overlap lock's expireAfter. A timeout kills the process without releasing
     * the lock, so a retry scheduled sooner than the lock's expiry finds it still held and is
     * discarded silently by WithoutOverlapping rather than being rescheduled.
     */
    public int $backoff = 7800;

    public function __construct(public readonly int $sourceId, public readonly string $mode = 'catalog')
    {
        $this->onQueue('catalog-imports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-source:{$this->sourceId}:{$this->mode}"))->expireAfter(7500)];
    }

    public function handle(CatalogSourceRegistry $registry, CatalogSourceIngestor $ingestor): void
    {
        $source = CatalogSource::query()->findOrFail($this->sourceId);
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'mode' => $this->mode,
            'status' => CatalogImportStatus::Running,
            'importer_name' => $source->connector_class,
            'started_at' => now(),
        ]);
        $source->update(['last_attempted_sync_at' => now()]);

        try {
            $connector = $registry->for($source);

            if ($connector instanceof ResumableCatalogSourceConnector) {
                $this->resumePreviousAttempt($connector, $run);
            }

            if ($connector instanceof CatalogSourceReleaseProvider) {
                $releasePayload = $connector->release($source, $this->mode);
                $releaseKey = trim((string) ($releasePayload['release_key'] ?? ''));
                if ($releaseKey !== '') {
                    $release = CatalogSourceRelease::query()->updateOrCreate(
                        ['catalog_source_id' => $source->id, 'release_key' => $releaseKey],
                        [
                            'published_at' => $releasePayload['published_at'] ?? null,
                            'retrieved_at' => $releasePayload['retrieved_at'] ?? now(),
                            'checksum_sha256' => $releasePayload['checksum_sha256'] ?? null,
                            'raw_object_path' => $releasePayload['raw_object_path'] ?? null,
                            'metadata' => $releasePayload['metadata'] ?? null,
                        ],
                    );
                    $run->update(['catalog_source_release_id' => $release->id]);
                }
            }

            foreach ($connector->records($source, $this->mode) as $row) {
                try {
                    $ingestor->store($source, $run, (array) $row);
                    $run->increment('fetched_count');
                    $run->increment('parsed_count');
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('failed_count');
                }

                if (($run->fetched_count + $run->failed_count) % 500 === 0) {
                    $run->touch();

                    // Persisted while the import is healthy, so a run killed by the job timeout
                    // leaves behind a position the next attempt can continue from instead of
                    // restarting a multi-hour feed at page one.
                    if ($connector instanceof ResumableCatalogSourceConnector) {
                        $run->update(['checkpoint' => $connector->checkpoint()]);
                    }
                }
            }

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? CatalogImportStatus::CompletedWithErrors : CatalogImportStatus::Completed,
                'checkpoint' => null,
                'finished_at' => now(),
            ]);
            $source->update(['last_successful_sync_at' => now()]);

            if ((bool) ($source->settings['auto_canonicalize'] ?? false)) {
                CanonicalizeCatalogSourceRecords::dispatch($source->id);
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => CatalogImportStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 4000),
                'checkpoint' => $connector instanceof ResumableCatalogSourceConnector
                    ? $connector->checkpoint()
                    : $run->checkpoint,
                'finished_at' => now(),
            ]);
            throw $exception;
        }
    }

    /**
     * A retry creates a new import run, so the position reached by the previous attempt has to
     * be carried across explicitly. Only an unfinished attempt is worth resuming; the connector
     * itself rejects a checkpoint that no longer matches its configured datasets.
     */
    private function resumePreviousAttempt(ResumableCatalogSourceConnector $connector, CatalogImportRun $run): void
    {
        $previous = CatalogImportRun::query()
            ->where('catalog_source_id', $run->catalog_source_id)
            ->where('mode', $run->mode)
            ->where('id', '<', $run->id)
            ->whereNotNull('checkpoint')
            ->whereIn('status', [CatalogImportStatus::Failed, CatalogImportStatus::Running])
            ->orderByDesc('id')
            ->first();

        if ($previous === null || ! is_array($previous->checkpoint)) {
            return;
        }

        $connector->resumeFrom($previous->checkpoint);
        $run->update(['checkpoint' => $previous->checkpoint]);
    }
}
