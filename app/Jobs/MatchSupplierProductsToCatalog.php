<?php

namespace App\Jobs;

use App\Catalog\Matching\SupplierCatalogPartMatcher;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class MatchSupplierProductsToCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

        // chunkById, not each(): matching moves a product out of the statuses this query
        // selects, and offset paging would then skip as many products as it just mapped.
        SupplierProduct::query()
            ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->when($this->supplierSyncRunId, fn ($q) => $q->where('last_supplier_sync_run_id', $this->supplierSyncRunId))
            ->whereIn('catalog_mapping_status', ['unmapped', 'unmatched', 'candidate'])
            ->where(fn ($q) => $q->whereNotNull('ean')->orWhereNotNull('manufacturer_part_number'))
            ->chunkById(500, function ($products) use ($matcher, &$remaining): bool {
                foreach ($products as $product) {
                    $matcher->match($product);

                    if (--$remaining <= 0) {
                        return false;
                    }
                }

                return true;
            });

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
}
