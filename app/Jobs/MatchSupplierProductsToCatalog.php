<?php

namespace App\Jobs;

use App\Catalog\Matching\SupplierCatalogPartMatcher;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class MatchSupplierProductsToCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Statuses a new run may revisit. Mapped articles, manual or automatic, are left alone. */
    public const REMATCHABLE = ['unmapped', 'unmatched', 'candidate', 'conflict'];

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(
        public readonly ?int $supplierId = null,
        public readonly int $limit = 10000,
        public readonly ?int $supplierSyncRunId = null,
    ) {
        $this->onQueue('catalog-matching');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('supplier-catalog-match:'.($this->supplierId ?? 'all')))->expireAfter(3900)];
    }

    public function handle(SupplierCatalogPartMatcher $matcher): void
    {
        $remaining = max(0, $this->limit);
        $outcomes = ['by_status' => [], 'by_reason' => [], 'invalid_gtins' => 0, 'processed' => 0];

        // chunkById, not each(): matching moves a product out of the statuses this query
        // selects, and offset paging would then skip as many products as it just mapped.
        SupplierProduct::query()
            ->with('supplier')
            ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->when($this->supplierSyncRunId, fn ($q) => $q->where('last_supplier_sync_run_id', $this->supplierSyncRunId))
            ->whereIn('catalog_mapping_status', self::REMATCHABLE)
            // An article identifiable only by its OE numbers or a TecDoc article used to
            // be skipped outright because it had neither an EAN nor an MPN column.
            ->where(fn ($q) => $q->whereNotNull('ean')->orWhereNotNull('manufacturer_part_number')->orWhereHas('identifiers'))
            ->chunkById(500, function ($products) use ($matcher, &$remaining, &$outcomes): bool {
                foreach ($products as $product) {
                    $this->tally($outcomes, $matcher->match($product));

                    if (--$remaining <= 0) {
                        return false;
                    }
                }

                return true;
            });

        if ($this->supplierSyncRunId) {
            $this->recordOnRun($outcomes);
        }

        if (! $this->supplierId || ! $this->supplierSyncRunId) {
            return;
        }

        $supplier = Supplier::query()->find($this->supplierId);
        if ($supplier
            && $supplier->allow_derived_data
            && (bool) ($supplier->settings['technical_promotion_enabled'] ?? false)) {
            PromoteSupplierTechnicalData::dispatch($supplier->id, $this->supplierSyncRunId);
        }
    }

    /**
     * @param  array<string, mixed>  $outcomes
     * @param  array{status: string, reasons: list<string>, invalid_gtin: bool}  $result
     */
    private function tally(array &$outcomes, array $result): void
    {
        $outcomes['processed']++;
        $outcomes['by_status'][$result['status']] = ($outcomes['by_status'][$result['status']] ?? 0) + 1;

        foreach ($result['reasons'] as $reason) {
            $outcomes['by_reason'][$reason] = ($outcomes['by_reason'][$reason] ?? 0) + 1;
        }

        if ($result['invalid_gtin']) {
            $outcomes['invalid_gtins']++;
        }
    }

    /**
     * Written onto the sync run that triggered matching, so "how much of this feed
     * did we actually recognise" is answered next to "how much did we receive".
     *
     * @param  array<string, mixed>  $outcomes
     */
    private function recordOnRun(array $outcomes): void
    {
        $run = SupplierSyncRun::query()->find($this->supplierSyncRunId);

        if (! $run) {
            return;
        }

        $run->update(['summary' => [...($run->summary ?? []), 'matching' => $outcomes]]);
    }
}
