<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class SupplierMatchingIndex extends Component
{
    use WithPagination;

    #[Url]
    public ?int $supplier = null;

    #[Url]
    public string $status = 'candidate';

    #[Url]
    public string $search = '';

    public function confirm(int $supplierProductId, int $catalogPartId): void
    {
        $product = SupplierProduct::query()->findOrFail($supplierProductId);
        $candidate = $product->catalogCandidates()->where('catalog_part_id', $catalogPartId)->firstOrFail();
        $product->update([
            'catalog_part_id' => $catalogPartId,
            'mapping_confidence' => 100,
            'catalog_mapping_status' => 'mapped_manual',
            'catalog_mapping_reason' => ['manual' => true, 'candidate_score' => $candidate->score, 'reasons' => $candidate->reasons],
            'catalog_mapped_at' => now(),
        ]);
        $candidate->update(['status' => 'confirmed']);
    }

    public function reject(int $candidateId): void
    {
        SupplierProductMatchCandidate::query()->findOrFail($candidateId)->update(['status' => 'rejected']);
    }

    public function render()
    {
        $products = SupplierProduct::query()
            ->with(['supplier', 'catalogPart.brand', 'catalogCandidates' => fn ($q) => $q->where('status', 'candidate')->with('catalogPart.brand')->orderByDesc('score')])
            ->when($this->supplier, fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->status, fn ($q) => $q->where('catalog_mapping_status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$this->search}%")->orWhere('supplier_sku', 'ilike', "%{$this->search}%")->orWhere('ean', 'ilike', "%{$this->search}%")->orWhere('manufacturer_part_number', 'ilike', "%{$this->search}%")))
            ->latest('id')->paginate(30);

        return view('livewire.admin.catalog-platform.supplier-matching-index', [
            'products' => $products,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }
}
