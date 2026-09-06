<?php

namespace App\Jobs;

use App\Catalog\Sources\CatalogSourceIngestor;
use App\Catalog\Sources\CatalogSourceRegistry;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
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

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

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
                }
            }

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? CatalogImportStatus::CompletedWithErrors : CatalogImportStatus::Completed,
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
                'finished_at' => now(),
            ]);
            throw $exception;
        }
    }
}
