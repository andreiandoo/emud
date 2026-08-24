<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ProductsIndex extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $category = '';

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function setStatus(int $productId, string $status): void
    {
        abort_unless(in_array($status, ['draft', 'review', 'active', 'archived'], true), 422);
        Product::query()->findOrFail($productId)->update([
            'status' => $status,
            'published_at' => $status === 'active' ? now() : null,
        ]);
    }

    public function render()
    {
        $products = Product::query()
            ->with(['brand', 'categories', 'variants'])
            ->withCount('supplierProducts')
            ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('sku', 'ilike', "%{$this->search}%")
                ->orWhere('manufacturer_part_number', 'ilike', "%{$this->search}%")))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->category, fn ($query) => $query->whereHas('categories', fn ($q) => $q->where('categories.id', $this->category)))
            ->latest()
            ->paginate(25);

        return view('livewire.admin.catalog.products-index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('full_path')->get(['id', 'name', 'full_path', 'depth']),
        ]);
    }
}
