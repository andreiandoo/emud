<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierSyncRun;
use App\Suppliers\ConnectorRegistry;
use App\Suppliers\Contracts\SupplierFeedArtifactProvider;
use App\Suppliers\SupplierCatalogImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncSupplierFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $supplierId, public readonly string $mode = 'catalog')
    {
        $this->onQueue('imports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("supplier:{$this->supplierId}:{$this->mode}"))->expireAfter(1900)];
    }

    public function handle(ConnectorRegistry $registry, SupplierCatalogImporter $importer): void
    {
        $supplier = Supplier::query()->findOrFail($this->supplierId);
        $run = SupplierSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'mode' => $this->mode,
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            if (! $supplier->allow_internal_data) {
                throw new RuntimeException("Supplier {$supplier->code} data rights do not permit internal ingestion.");
            }

            $connector = $registry->for($supplier);
            foreach ($connector->records($supplier, $this->mode) as $record) {
                try {
                    $result = $importer->import($supplier, $record, $this->mode);
                    $run->increment('processed');
                    if ($result['created']) {
                        $run->increment('created_count');
                    } elseif ($result['updated']) {
                        $run->increment('updated_count');
                    } else {
                        $run->increment('skipped_count');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('processed');
                    $run->increment('failed_count');
                }
                if ($run->processed % 100 === 0) {
                    $run->touch();
                }
            }

            if ($connector instanceof SupplierFeedArtifactProvider && ($artifact = $connector->lastArtifact())) {
                SupplierFeedArtifact::query()->create([
                    'supplier_id' => $supplier->id,
                    'supplier_sync_run_id' => $run->id,
                    'mode' => $artifact['mode'] ?? $this->mode,
                    'source_path' => $artifact['source_path'],
                    'filename' => $artifact['filename'] ?? null,
                    'size_bytes' => $artifact['size_bytes'] ?? null,
                    'source_modified_at' => isset($artifact['source_modified_at']) ? now()->setTimestamp((int) $artifact['source_modified_at']) : null,
                    'checksum_sha256' => $artifact['checksum_sha256'],
                    'retrieved_at' => $artifact['retrieved_at'] ?? now(),
                    'metadata' => $artifact['metadata'] ?? null,
                ]);
            }

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? SyncStatus::CompletedWithErrors : SyncStatus::Completed,
                'finished_at' => now(),
            ]);
            $supplier->update(['last_successful_sync_at' => now()]);

            if ($this->mode === 'catalog' && (bool) ($supplier->settings['match_catalog_parts'] ?? true)) {
                MatchSupplierProductsToCatalog::dispatch($supplier->id);
            }
        } catch (Throwable $exception) {
            $run->update([
                'status' => SyncStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 4000),
                'finished_at' => now(),
            ]);
            throw $exception;
        }
    }
}
