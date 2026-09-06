<?php

namespace App\Jobs;

use App\Catalog\Sources\CatalogSourceIngestor;
use App\Catalog\Sources\CatalogSourceRegistry;
use App\Enums\CatalogImportStatus;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
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
            foreach ($registry->for($source)->records($source, $this->mode) as $row) {
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
