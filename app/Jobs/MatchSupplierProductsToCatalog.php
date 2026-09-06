<?php

namespace App\Jobs;

use App\Catalog\Matching\SupplierCatalogPartMatcher;
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

    public function __construct(public readonly ?int $supplierId = null, public readonly int $limit = 10000)
    {
        $this->onQueue('catalog-matching');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('supplier-catalog-match:'.($this->supplierId ?? 'all')))->expireAfter(3900)];
    }

    public function handle(SupplierCatalogPartMatcher $matcher): void
    {
        SupplierProduct::query()
            ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->whereIn('catalog_mapping_status', ['unmapped', 'unmatched', 'candidate'])
            ->where(fn ($q) => $q->whereNotNull('ean')->orWhereNotNull('manufacturer_part_number'))
            ->orderBy('id')
            ->limit($this->limit)
            ->each(fn (SupplierProduct $product) => $matcher->match($product));
    }
}
