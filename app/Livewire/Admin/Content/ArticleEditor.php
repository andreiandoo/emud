<?php

namespace App\Livewire\Admin\Content;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::admin')]
class ArticleEditor extends Component
{
    use WithFileUploads;

    public ?Article $article = null;
    public string $title = '';
    public string $slug = '';
    public ?int $articleCategoryId = null;
    public string $excerpt = '';
    public string $content = '';
    public string $status = 'draft';
    public bool $isFeatured = false;
    public $featuredImage;
    public string $featuredImageAlt = '';
    public string $seoTitle = '';
    public string $seoDescription = '';
    public string $canonicalUrl = '';
    public bool $robotsIndex = true;
    public bool $robotsFollow = true;

    public function mount(?Article $article = null): void
    {
        if (! $article?->exists) return;
        $this->article = $article;
        foreach (['title', 'slug', 'excerpt', 'content', 'status'] as $field) $this->{$field} = $article->{$field} ?? '';
        $this->articleCategoryId = $article->article_category_id;
        $this->isFeatured = $article->is_featured;
        $this->featuredImageAlt = $article->featured_image_alt ?? '';
        $this->seoTitle = $article->seo_title ?? '';
        $this->seoDescription = $article->seo_description ?? '';
        $this->canonicalUrl = $article->canonical_url ?? '';
        $this->robotsIndex = $article->robots_index;
        $this->robotsFollow = $article->robots_follow;
    }

    public function updatedTitle(): void
    {
        if (! $this->article || $this->slug === '') $this->slug = Str::slug($this->title);
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'unique:articles,slug,'.($this->article?->id ?? 'NULL')],
            'articleCategoryId' => ['nullable', 'exists:article_categories,id'],
            'excerpt' => ['nullable', 'string'], 'content' => ['required', 'string'],
            'status' => ['required', 'in:draft,review,published,archived'],
            'featuredImage' => ['nullable', 'image', 'max:8192'],
            'featuredImageAlt' => ['nullable', 'string', 'max:255'],
            'seoTitle' => ['nullable', 'string', 'max:255'],
            'seoDescription' => ['nullable', 'string', 'max:320'],
            'canonicalUrl' => ['nullable', 'url', 'max:2048'],
        ]);
        $imagePath = $this->featuredImage?->store('articles', 'public') ?? $this->article?->featured_image_path;
        $this->article = Article::updateOrCreate(['id' => $this->article?->id], [
            'author_id' => auth()->id(), 'article_category_id' => $data['articleCategoryId'],
            'title' => $data['title'], 'slug' => $data['slug'], 'excerpt' => $data['excerpt'] ?: null,
            'content' => $data['content'], 'status' => $data['status'], 'is_featured' => $this->isFeatured,
            'featured_image_path' => $imagePath, 'featured_image_alt' => $data['featuredImageAlt'] ?: null,
            'seo_title' => $data['seoTitle'] ?: null, 'seo_description' => $data['seoDescription'] ?: null,
            'canonical_url' => $data['canonicalUrl'] ?: null, 'robots_index' => $this->robotsIndex,
            'robots_follow' => $this->robotsFollow,
            'published_at' => $data['status'] === 'published' ? ($this->article?->published_at ?? now()) : null,
        ]);
        session()->flash('success', 'Articolul a fost salvat.');
        $this->redirectRoute('admin.articles.edit', $this->article, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.content.article-editor', ['categories' => ArticleCategory::where('is_active', true)->orderBy('name')->get()]);
    }
}
