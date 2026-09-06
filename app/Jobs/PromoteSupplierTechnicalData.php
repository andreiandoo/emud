<?php

namespace App\Jobs;

use App\Catalog\Canonicalization\CatalogCanonicalizerRegistry;
use App\Catalog\Relations\CatalogPartRelationResolver;
use App\Catalog\SupplierPromotion\SupplierTechnicalSourceBridge;
use App\Enums\CatalogImportStatus;
use App\Models\CatalogConflict;
use App\Models\CatalogImportRun;
use App\Models\Supplier;
use App\Models\SupplierFeedArtifact;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PromoteSupplierTechnicalData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 2;

    public array $backoff = [120, 600];

    public function __construct(
        public readonly int $supplierId,
        public readonly ?int $supplierSyncRunId = null,
        public readonly int $limit = 50000,
    ) {
        $this->onQueue('catalog-canonicalization');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("supplier-technical-promotion:{$this->supplierId}"))->expireAfter(7500)];
    }

    public function handle(
        SupplierTechnicalSourceBridge $bridge,
        CatalogCanonicalizerRegistry $registry,
        CatalogPartRelationResolver $relations,
    ): void {
        $supplier = Supplier::query()->findOrFail($this->supplierId);

        if (! $supplier->allow_internal_data || ! $supplier->allow_derived_data) {
            SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('technical_promotion_status', 'pending')
                ->update(['technical_promotion_status' => 'blocked_rights']);

            return;
        }

        if (! (bool) ($supplier->settings['technical_promotion_enabled'] ?? false)) {
            return;
        }

        $syncRun = $this->resolveSyncRun($supplier);
        $artifact = SupplierFeedArtifact::query()
            ->where('supplier_sync_run_id', $syncRun->id)
            ->where('mode', 'catalog')
            ->latest('id')
            ->first();
        $source = $bridge->sourceFor($supplier);
        $release = $bridge->releaseFor($source, $syncRun, $artifact);
        $canonicalizer = $registry->for($source);
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'catalog_source_release_id' => $release->id,
            'mode' => 'supplier_promotion',
            'status' => CatalogImportStatus::Running,
            'importer_name' => self::class,
            'started_at' => now(),
        ]);
        $source->update(['last_attempted_sync_at' => now()]);
        $allowCreateParts = (bool) ($supplier->settings['technical_promotion_create_parts'] ?? false);

        try {
            SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('technical_promotion_status', 'pending')
                ->whereNotNull('technical_payload')
                ->when($this->supplierSyncRunId, fn ($query) => $query->where('last_supplier_sync_run_id', $syncRun->id))
                ->orderBy('id')
                ->limit($this->limit)
                ->get()
                ->each(function (SupplierProduct $product) use ($bridge, $canonicalizer, $supplier, $syncRun, $artifact, $release, $run, $allowCreateParts): void {
                    $run->increment('fetched_count');
                    $run->increment('parsed_count');

                    try {
                        $existingIdentity = $bridge->existingCanonicalIdentity($product);
                        if (! $allowCreateParts && ! $existingIdentity) {
                            $product->update([
                                'technical_promotion_status' => 'awaiting_canonical_part',
                                'technical_promotion_error' => 'No canonical brand+MPN identity exists and create-parts promotion is disabled.',
                            ]);
                            $run->increment('skipped_count');

                            return;
                        }

                        $record = $bridge->stage($supplier->technicalCatalogSource ?? $bridge->sourceFor($supplier), $run, $product, $syncRun, $artifact, $release);
                        $result = $canonicalizer->canonicalize($record);
                        $record->update([
                            'mapping_status' => $result->status,
                            'mapping_notes' => $result->message,
                            'canonical_entity_type' => $result->entityType,
                            'canonical_entity_id' => $result->entityId,
                            'mapping_confidence' => $result->confidence,
                        ]);

                        if ($result->status !== 'published' || $result->entityType !== 'catalog_part' || ! $result->entityId) {
                            $product->update([
                                'technical_promotion_status' => 'skipped',
                                'technical_promotion_error' => $result->message,
                            ]);
                            $run->increment('skipped_count');

                            return;
                        }

                        if ($product->catalog_part_id && $product->catalog_part_id !== $result->entityId) {
                            CatalogConflict::query()->firstOrCreate([
                                'entity_type' => 'supplier_product',
                                'entity_id' => $product->id,
                                'field_or_relation' => 'technical_identity_mismatch',
                                'status' => 'open',
                            ], [
                                'severity' => 'error',
                                'details' => [
                                    'supplier_id' => $supplier->id,
                                    'existing_catalog_part_id' => $product->catalog_part_id,
                                    'technical_catalog_part_id' => $result->entityId,
                                    'catalog_source_record_id' => $record->id,
                                ],
                            ]);
                            $product->update([
                                'technical_promotion_status' => 'conflict',
                                'technical_promotion_error' => 'Supplier commerce mapping conflicts with technical brand+MPN identity.',
                            ]);
                            $run->increment('skipped_count');

                            return;
                        }

                        $product->update([
                            'catalog_part_id' => $result->entityId,
                            'mapping_confidence' => max((float) ($product->mapping_confidence ?? 0), (float) ($result->confidence ?? 0)),
                            'catalog_mapping_status' => $product->catalog_mapping_status === 'mapped_auto' ? 'mapped_auto' : 'mapped_promoted',
                            'catalog_mapping_reason' => array_replace($product->catalog_mapping_reason ?? [], ['technical_source' => $record->source->code]),
                            'catalog_mapped_at' => $product->catalog_mapped_at ?? now(),
                            'technical_promotion_status' => 'promoted',
                            'technical_promotion_error' => null,
                            'technical_promoted_at' => now(),
                        ]);
                        $run->increment('matched_count');
                        $run->increment('published_count');
                    } catch (Throwable $exception) {
                        report($exception);
                        $product->update([
                            'technical_promotion_status' => 'failed',
                            'technical_promotion_error' => Str::limit($exception->getMessage(), 4000),
                        ]);
                        $run->increment('failed_count');
                    }
                });

            $run->refresh()->update([
                'status' => $run->failed_count > 0 ? CatalogImportStatus::CompletedWithErrors : CatalogImportStatus::Completed,
                'finished_at' => now(),
            ]);
            $source->update(['last_successful_sync_at' => now()]);
            $relations->resolvePending((int) ($supplier->settings['technical_relation_resolution_limit'] ?? 10000));
        } catch (Throwable $exception) {
            $run->update([
                'status' => CatalogImportStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 4000),
                'finished_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function resolveSyncRun(Supplier $supplier): SupplierSyncRun
    {
        $query = SupplierSyncRun::query()
            ->where('supplier_id', $supplier->id)
            ->where('mode', 'catalog');

        $syncRun = $this->supplierSyncRunId
            ? $query->whereKey($this->supplierSyncRunId)->first()
            : $query->latest('id')->first();

        if (! $syncRun) {
            throw new RuntimeException("No catalog supplier sync run exists for {$supplier->code}.");
        }

        return $syncRun;
    }
}
