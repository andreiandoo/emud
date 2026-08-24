<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierSyncRun;
use App\Suppliers\ConnectorRegistry;
use App\Suppliers\SupplierCatalogImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
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
            foreach ($registry->for($supplier)->records($supplier, $this->mode) as $record) {
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

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? SyncStatus::CompletedWithErrors : SyncStatus::Completed,
                'finished_at' => now(),
            ]);
            $supplier->update(['last_successful_sync_at' => now()]);
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
