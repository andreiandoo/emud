<?php

namespace App\Livewire\Admin\Content;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ArticlesIndex extends Component
{
    use WithPagination;

    public string $categoryName = '';

    public ?int $editingCategoryId = null;

    public function saveCategory(): void
    {
        $data = $this->validate(['categoryName' => ['required', 'string', 'max:255']]);
        ArticleCategory::updateOrCreate(['id' => $this->editingCategoryId], [
            'name' => $data['categoryName'], 'slug' => Str::slug($data['categoryName']), 'is_active' => true,
        ]);
        $this->reset(['categoryName', 'editingCategoryId']);
    }

    public function editCategory(int $id): void
    {
        $category = ArticleCategory::findOrFail($id);
        $this->editingCategoryId = $id;
        $this->categoryName = $category->name;
    }

    public function deleteCategory(int $id): void
    {
        ArticleCategory::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.content.articles-index', [
            'articles' => Article::with(['category', 'author'])->latest()->paginate(20),
            'categories' => ArticleCategory::withCount('articles')->orderBy('position')->orderBy('name')->get(),
        ]);
    }
}
