<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::admin')]
class CategoriesIndex extends Component
{
    #[Url]
    public string $search = '';

    public function toggle(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.catalog.categories-index', [
            'categories' => Category::query()
                ->withCount(['products', 'attributes'])
                ->when($this->search, fn ($query) => $query->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('full_path')
                ->get(),
        ]);
    }
}
