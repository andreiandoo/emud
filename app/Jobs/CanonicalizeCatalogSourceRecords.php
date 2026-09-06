<?php

namespace App\Jobs;

use App\Catalog\Canonicalization\CatalogCanonicalizerRegistry;
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

class CanonicalizeCatalogSourceRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 2;
    public array $backoff = [120, 600];

    public function __construct(public readonly int $sourceId, public readonly int $limit = 50000)
    {
        $this->onQueue('catalog-canonicalization');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-canonicalize:{$this->sourceId}"))->expireAfter(7500)];
    }

    public function handle(CatalogCanonicalizerRegistry $registry): void
    {
        $source = CatalogSource::query()->findOrFail($this->sourceId);
        $canonicalizer = $registry->for($source);
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'mode' => 'canonicalize',
            'status' => CatalogImportStatus::Running,
            'importer_name' => $canonicalizer::class,
            'started_at' => now(),
        ]);

        try {
            $source->records()
                ->whereIn('mapping_status', ['unprocessed', 'candidate', 'failed'])
                ->orderBy('id')
                ->limit($this->limit)
                ->each(function ($record) use ($canonicalizer, $run): void {
                    try {
                        $result = $canonicalizer->canonicalize($record);
                        $record->update([
                            'mapping_status' => $result->status,
                            'mapping_notes' => $result->message,
                            'canonical_entity_type' => $result->entityType,
                            'canonical_entity_id' => $result->entityId,
                            'mapping_confidence' => $result->confidence,
                        ]);

                        if ($result->status === 'published') {
                            $run->increment('matched_count');
                            $run->increment('published_count');
                        } else {
                            $run->increment('skipped_count');
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $record->update(['mapping_status' => 'failed', 'mapping_notes' => Str::limit($exception->getMessage(), 4000)]);
                        $run->increment('failed_count');
                    }
                });

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? CatalogImportStatus::CompletedWithErrors : CatalogImportStatus::Completed,
                'finished_at' => now(),
            ]);
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
