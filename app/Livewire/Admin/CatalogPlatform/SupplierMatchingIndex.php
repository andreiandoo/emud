<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Catalog\Matching\BrandResolver;
use App\Catalog\Matching\SupplierCatalogPartMatcher;
use App\Jobs\MatchSupplierProductsToCatalog;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMatchCandidate;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class SupplierMatchingIndex extends Component
{
    use WithPagination;

    /** Articles re-matched on the spot after an alias; the rest wait for the next run. */
    private const ALIAS_REMATCH_LIMIT = 200;

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

    /**
     * Teaches the catalogue that the supplier's brand spelling is the candidate's brand,
     * then re-matches that supplier's articles written the same way.
     *
     * This is the one confirmation that pays for itself many times over: a supplier that
     * writes "FEBI" does it on every febi bilstein article, and each of them was stuck at
     * a brand-less MPN score until someone said the two names are one manufacturer.
     */
    public function aliasBrand(int $candidateId, BrandResolver $brands, SupplierCatalogPartMatcher $matcher): void
    {
        $candidate = SupplierProductMatchCandidate::query()->with(['supplierProduct', 'catalogPart'])->findOrFail($candidateId);
        $product = $candidate->supplierProduct;
        $brandId = $candidate->catalogPart?->brand_id;

        if (! $product || blank($product->raw_brand) || ! $brandId) {
            session()->flash('status', 'Aliasul are nevoie de un brand la furnizor și de un brand pe piesa candidată.');

            return;
        }

        $brands->alias($product->raw_brand, (int) $brandId, Auth::id());

        $rematched = 0;
        SupplierProduct::query()
            ->where('supplier_id', $product->supplier_id)
            ->where('raw_brand', $product->raw_brand)
            ->whereIn('catalog_mapping_status', MatchSupplierProductsToCatalog::REMATCHABLE)
            ->orderBy('id')
            ->limit(self::ALIAS_REMATCH_LIMIT)
            ->get()
            ->each(function (SupplierProduct $sibling) use ($matcher, &$rematched): void {
                $matcher->match($sibling);
                $rematched++;
            });

        // Anything past the limit is picked up by the next matching run instead of
        // holding this request open for a whole catalogue.
        MatchSupplierProductsToCatalog::dispatch($product->supplier_id);

        session()->flash('status', "„{$product->raw_brand}” este acum recunoscut ca {$candidate->catalogPart->brand?->name}. {$rematched} articole re-potrivite imediat.");
    }

    public function render()
    {
        $products = SupplierProduct::query()
            ->with([
                'supplier',
                'catalogPart.brand',
                'identifiers',
                'catalogCandidates' => fn ($q) => $q->where('status', 'candidate')->with('catalogPart.brand')->orderByDesc('score'),
            ])
            ->when($this->supplier, fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->status, fn ($q) => $q->where('catalog_mapping_status', $this->status))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$this->search}%")->orWhere('supplier_sku', 'ilike', "%{$this->search}%")->orWhere('ean', 'ilike', "%{$this->search}%")->orWhere('manufacturer_part_number', 'ilike', "%{$this->search}%")))
            ->latest('id')->paginate(30);

        return view('livewire.admin.catalog-platform.supplier-matching-index', [
            'products' => $products,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => [
                'candidate' => 'De verificat',
                'conflict' => 'Conflict de identificatori',
                'unmatched' => 'Fără potrivire',
                'unmapped' => 'Neprocesat',
                'mapped_auto' => 'Mapat automat',
                'mapped_manual' => 'Mapat manual',
            ],
        ]);
    }
}
