<?php

namespace App\Livewire\Storefront;

use App\Models\Article;
use App\Models\ArticleCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::storefront', ['fullWidth' => true])]
class Guides extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $category = '';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.storefront.guides', [
            'articles' => Article::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->when($this->category !== '', fn ($query) => $query->whereHas('category', fn ($c) => $c->where('slug', $this->category)))
                ->with('category')
                ->orderByDesc('published_at')
                ->paginate(12),
            'categories' => ArticleCategory::query()->where('is_active', true)->orderBy('position')->orderBy('name')->get(),
        ]);
    }
}
