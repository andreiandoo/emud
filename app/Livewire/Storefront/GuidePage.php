<?php

namespace App\Livewire\Storefront;

use App\Models\Article;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class GuidePage extends Component
{
    public Article $article;

    /**
     * Resolved here rather than by route binding so a draft or a future-dated article is a 404
     * instead of being readable by anyone who guesses the slug.
     */
    public function mount(string $slug): void
    {
        $this->article = Article::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category', 'author', 'blocks', 'vehicles.make', 'vehicles.model', 'vehicles.generation'])
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.guide-page');
    }
}
