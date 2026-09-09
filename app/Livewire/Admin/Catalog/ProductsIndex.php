<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ProductsIndex extends Component
{
    use WithPagination;

    /** @var array<string, string> */
    public const TABS = [
        '' => 'Toate',
        'draft' => 'Ciorne',
        'review' => 'De verificat',
        'active' => 'Publicate',
        'archived' => 'Arhivate',
    ];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    /** @var list<int> */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category'], true)) {
            $this->selected = [];
            $this->resetPage();
        }
    }

    public function selectPage(): void
    {
        $this->selected = $this->query()->paginate(25)->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->category = '';
        $this->selected = [];
        $this->resetPage();
    }

    public function setStatus(int $productId, string $status): void
    {
        $this->applyStatus([$productId], $status);
    }

    public function bulkStatus(string $status): void
    {
        $count = count($this->selected);
        $this->applyStatus($this->selected, $status);
        $this->selected = [];

        session()->flash('success', trans_choice('S-a actualizat :count produs.|S-au actualizat :count produse.', $count, ['count' => $count]));
    }

    public function render()
    {
        return view('livewire.admin.catalog.products-index', [
            // Fitments are eager-loaded rather than counted alone: the column names the first
            // couple of vehicles, and a count with no names answers nothing useful.
            'products' => $this->query()
                ->with(['brand', 'variants', 'media', 'fitments.make', 'fitments.model', 'fitments.generation'])
                ->withCount(['supplierProducts', 'fitments'])
                ->paginate(25),
            'categories' => Category::query()->orderBy('full_path')->get(['id', 'name', 'full_path', 'depth']),
            'tabs' => self::TABS,
            'counts' => $this->countsByStatus(),
            'totals' => $this->totals(),
        ]);
    }

    /** @param  list<int>  $ids */
    private function applyStatus(array $ids, string $status): void
    {
        abort_unless(in_array($status, ['draft', 'review', 'active', 'archived'], true), 422);

        Product::query()->whereIn('id', $ids)->update([
            'status' => $status,
            'published_at' => $status === 'active' ? now() : null,
        ]);
    }

    private function query(): Builder
    {
        return Product::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('sku', 'ilike', "%{$this->search}%")
                ->orWhere('manufacturer_part_number', 'ilike', "%{$this->search}%")))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query) => $query->whereHas('categories', fn (Builder $inner) => $inner->where('categories.id', $this->category)))
            ->latest('id');
    }

    /** @return array<string, int> */
    private function countsByStatus(): array
    {
        /** @var Collection<string, int> $counts */
        $counts = Product::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['' => (int) $counts->sum()] + $counts->map(fn ($total) => (int) $total)->all();
    }

    /** @return array<string, int> */
    private function totals(): array
    {
        $filtered = $this->query()->reorder();

        return [
            'count' => (clone $filtered)->count(),
            'withoutOffers' => (clone $filtered)->whereDoesntHave('supplierProducts')->count(),
            'withoutMedia' => (clone $filtered)->whereDoesntHave('media')->count(),
        ];
    }
}
