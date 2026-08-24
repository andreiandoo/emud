<?php

namespace App\Livewire\Admin\Catalog;

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class CategoriesIndex extends Component
{
    use WithFileUploads;

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public ?int $parentId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public $image;

    public ?string $currentImage = null;

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $canonicalUrl = '';

    public bool $robotsIndex = true;

    public bool $robotsFollow = true;

    public bool $isActive = true;

    public bool $isVisibleInMenu = true;

    public function updatedName(): void
    {
        if (! $this->editingId || $this->slug === '') {
            $this->slug = Str::slug($this->name);
        }
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->parentId = $category->parent_id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description ?? '';
        $this->currentImage = $category->image_path;
        $this->seoTitle = $category->seo_title ?? '';
        $this->seoDescription = $category->seo_description ?? '';
        $this->canonicalUrl = $category->canonical_url ?? '';
        $this->robotsIndex = $category->robots_index;
        $this->robotsFollow = $category->robots_follow;
        $this->isActive = $category->is_active;
        $this->isVisibleInMenu = $category->is_visible_in_menu;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255'],
            'parentId' => ['nullable', 'exists:categories,id', function ($attribute, $value, $fail): void {
                if ($this->editingId && (int) $value === $this->editingId) {
                    $fail('O categorie nu poate fi propriul părinte.');
                }
                if ($this->editingId && $value) {
                    $current = Category::find($this->editingId);
                    $parent = Category::find($value);
                    if ($current && $parent && str_starts_with($parent->full_path.'/', $current->full_path.'/')) {
                        $fail('Categoria nu poate fi mutată într-unul dintre descendenții ei.');
                    }
                }
            }],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:4096'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:320'],
            'canonicalUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        $parent = $this->parentId ? Category::findOrFail($this->parentId) : null;
        $category = $this->editingId ? Category::findOrFail($this->editingId) : new Category;
        $imagePath = $this->image?->store('categories', 'public') ?? $category->image_path;
        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'full_path' => $parent ? "{$parent->full_path}/{$validated['slug']}" : $validated['slug'],
            'depth' => $parent ? $parent->depth + 1 : 0,
            'position' => $category->exists ? $category->position : ((int) Category::where('parent_id', $parent?->id)->max('position') + 1),
            'description' => $validated['description'] ?: null,
            'image_path' => $imagePath,
            'seo_title' => $validated['seoTitle'] ?: null,
            'seo_description' => $validated['seoDescription'] ?: null,
            'canonical_url' => $validated['canonicalUrl'] ?: null,
            'robots_index' => $this->robotsIndex,
            'robots_follow' => $this->robotsFollow,
            'is_active' => $this->isActive,
            'is_visible_in_menu' => $this->isVisibleInMenu,
        ])->save();

        $this->refreshDescendantPaths($category);
        $this->resetForm();
        session()->flash('success', 'Categoria a fost salvată.');
    }

    public function move(int $categoryId, string $direction): void
    {
        $category = Category::findOrFail($categoryId);
        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'desc' : 'asc';
        $sibling = Category::where('parent_id', $category->parent_id)
            ->where('position', $operator, $category->position)->orderBy('position', $order)->first();
        if ($sibling) {
            [$category->position, $sibling->position] = [$sibling->position, $category->position];
            $category->save();
            $sibling->save();
        }
    }

    public function delete(int $categoryId): void
    {
        $category = Category::withCount(['children', 'products'])->findOrFail($categoryId);
        abort_if($category->children_count || $category->products_count, 422, 'Categoria are subcategorii sau produse.');
        $category->delete();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'parentId', 'name', 'slug', 'description', 'image', 'currentImage', 'seoTitle', 'seoDescription', 'canonicalUrl']);
        $this->resetValidation();
        $this->robotsIndex = $this->robotsFollow = $this->isActive = $this->isVisibleInMenu = true;
    }

    private function refreshDescendantPaths(Category $category): void
    {
        $category->load('children');
        foreach ($category->children as $child) {
            $child->update(['full_path' => "{$category->full_path}/{$child->slug}", 'depth' => $category->depth + 1]);
            $this->refreshDescendantPaths($child);
        }
    }

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
            'parentOptions' => Category::query()->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))->orderBy('full_path')->get(),
        ]);
    }
}
