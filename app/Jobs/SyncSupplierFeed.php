<?php

namespace App\Jobs;

use App\Enums\SupplierSyncErrorType;
use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierSyncError;
use App\Models\SupplierSyncRun;
use App\Suppliers\ConnectorRegistry;
use App\Suppliers\Contracts\ReportsFeedIssues;
use App\Suppliers\Contracts\SupplierFeedArtifactProvider;
use App\Suppliers\Data\SupplierFeedIssue;
use App\Suppliers\SupplierCatalogImporter;
use App\Suppliers\SupplierCatalogRetirement;
use App\Suppliers\SupplierFeedGuard;
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

    /** Largest raw payload kept per error row, so one enormous record cannot bloat the table. */
    private const MAX_PAYLOAD_BYTES = 8192;

    /** Counters are held in memory and flushed in batches instead of one UPDATE per feed row. */
    private const COUNTER_FLUSH_EVERY = 200;

    public int $timeout = 1800;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    /** @var array<string, int> */
    private array $counters = [
        'received_count' => 0,
        'processed' => 0,
        'created_count' => 0,
        'updated_count' => 0,
        'skipped_count' => 0,
        'failed_count' => 0,
        'rejected_count' => 0,
    ];

    /** @var array<string, int> */
    private array $errorsByType = [];

    public function __construct(public readonly int $supplierId, public readonly string $mode = 'catalog')
    {
        $this->onQueue('imports');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("supplier:{$this->supplierId}:{$this->mode}"))->expireAfter(1900)];
    }

    public function handle(
        ConnectorRegistry $registry,
        SupplierCatalogImporter $importer,
        SupplierFeedGuard $guard,
        SupplierCatalogRetirement $retirement,
    ): void {
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
                $this->counters['received_count']++;
                $this->counters['processed']++;

                try {
                    $result = $importer->import($supplier, $record, $this->mode, $run);

                    if ($result['created']) {
                        $this->counters['created_count']++;
                    } elseif ($result['updated']) {
                        $this->counters['updated_count']++;
                    } else {
                        $this->counters['skipped_count']++;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $this->counters['failed_count']++;
                    $this->recordError($run, $supplier, SupplierSyncErrorType::Persistence, $exception->getMessage(), $record->externalId, $record->raw);
                }

                if ($connector instanceof ReportsFeedIssues) {
                    $this->drainIssues($connector, $run, $supplier);
                }

                if ($this->counters['processed'] % self::COUNTER_FLUSH_EVERY === 0) {
                    $this->flushCounters($run);
                }
            }

            if ($connector instanceof ReportsFeedIssues) {
                $this->drainIssues($connector, $run, $supplier);
            }

            $this->flushCounters($run);
            $this->storeArtifact($connector, $run, $supplier);

            $guardResult = $guard->evaluate($supplier, $this->mode, $this->counters['received_count']);

            if ($guardResult['tripped']) {
                $this->recordError(
                    $run,
                    $supplier,
                    SupplierSyncErrorType::Transport,
                    "Feedul a returnat {$guardResult['received']} rânduri față de {$guardResult['baseline']} la ultima rulare reușită. Rularea nu este tratată ca un catalog complet.",
                );

                $run->refresh()->update([
                    'status' => SyncStatus::AbortedGuard,
                    'finished_at' => now(),
                    'summary' => $this->summary($guardResult, null),
                ]);

                return;
            }

            // Retirement only makes sense for a full catalogue feed. A stock or price
            // file lists a subset by design, so absence there proves nothing.
            $retirementResult = $this->mode === 'catalog' ? $retirement->retireMissing($supplier) : null;

            $run->refresh()->update([
                'status' => $this->counters['failed_count'] > 0 ? SyncStatus::CompletedWithErrors : SyncStatus::Completed,
                'finished_at' => now(),
                'retired_count' => $retirementResult['retired'] ?? 0,
                'summary' => $this->summary($guardResult, $retirementResult),
            ]);
            $supplier->update(['last_successful_sync_at' => now()]);

            if ($this->mode === 'catalog' && (bool) ($supplier->settings['match_catalog_parts'] ?? true)) {
                MatchSupplierProductsToCatalog::dispatch($supplier->id, 10000, $run->id);
            } elseif ($this->mode === 'catalog' && (bool) ($supplier->settings['technical_promotion_enabled'] ?? false)) {
                PromoteSupplierTechnicalData::dispatch($supplier->id, $run->id);
            }
        } catch (Throwable $exception) {
            $this->flushCounters($run);
            $this->recordError($run, $supplier, SupplierSyncErrorType::Transport, $exception->getMessage());

            $run->refresh()->update([
                'status' => SyncStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 4000),
                'finished_at' => now(),
                'summary' => $this->summary(null, null),
            ]);

            throw $exception;
        }
    }

    private function drainIssues(ReportsFeedIssues $connector, SupplierSyncRun $run, Supplier $supplier): void
    {
        foreach ($connector->takeIssues() as $issue) {
            $this->counters['rejected_count']++;
            $this->counters['received_count']++;
            $this->recordIssue($run, $supplier, $issue);
        }
    }

    private function recordIssue(SupplierSyncRun $run, Supplier $supplier, SupplierFeedIssue $issue): void
    {
        $this->recordError($run, $supplier, $issue->type, $issue->message, $issue->externalIdentifier, $issue->raw);
    }

    /** @param array<string, mixed> $raw */
    private function recordError(
        SupplierSyncRun $run,
        Supplier $supplier,
        SupplierSyncErrorType $type,
        string $message,
        ?string $externalIdentifier = null,
        array $raw = [],
    ): void {
        $this->errorsByType[$type->value] = ($this->errorsByType[$type->value] ?? 0) + 1;

        $limit = (int) config('emud.suppliers.max_errors_per_run');
        if ($limit > 0 && array_sum($this->errorsByType) > $limit) {
            // Past this point the pattern is established; storing another hundred
            // thousand identical rows helps nobody and costs a lot of disk.
            return;
        }

        try {
            SupplierSyncError::query()->create([
                'supplier_sync_run_id' => $run->id,
                'supplier_id' => $supplier->id,
                'external_identifier' => $externalIdentifier ? Str::limit($externalIdentifier, 250, '') : null,
                'error_type' => $type,
                'message' => Str::limit($message, 2000),
                'raw_payload' => $this->truncatePayload($raw),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Failing to log a failure must never abort the import itself.
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function truncatePayload(array $raw): ?array
    {
        if ($raw === []) {
            return null;
        }

        $encoded = json_encode($raw);

        if ($encoded !== false && strlen($encoded) <= self::MAX_PAYLOAD_BYTES) {
            return $raw;
        }

        return [
            '_truncated' => true,
            '_original_bytes' => $encoded === false ? null : strlen($encoded),
            '_preview' => Str::limit((string) $encoded, self::MAX_PAYLOAD_BYTES),
        ];
    }

    private function flushCounters(SupplierSyncRun $run): void
    {
        SupplierSyncRun::query()->whereKey($run->id)->update([...$this->counters, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>|null  $guardResult
     * @param  array<string, mixed>|null  $retirementResult
     * @return array<string, mixed>
     */
    private function summary(?array $guardResult, ?array $retirementResult): array
    {
        return array_filter([
            'records' => $this->counters,
            'errors_by_type' => $this->errorsByType,
            'error_rate' => $this->counters['received_count'] > 0
                ? round(($this->counters['failed_count'] + $this->counters['rejected_count']) / $this->counters['received_count'], 4)
                : 0.0,
            'guard' => $guardResult,
            'retirement' => $retirementResult,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function storeArtifact(object $connector, SupplierSyncRun $run, Supplier $supplier): void
    {
        if (! $connector instanceof SupplierFeedArtifactProvider || ! ($artifact = $connector->lastArtifact())) {
            return;
        }

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
}
