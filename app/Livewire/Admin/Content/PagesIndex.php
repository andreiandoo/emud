<?php

namespace App\Livewire\Admin\Content;

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class PagesIndex extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $content = '';

    public string $status = 'draft';

    public string $version = '';

    public ?string $effective_from = null;

    public bool $show_in_footer = true;

    public int $position = 0;

    public string $seo_title = '';

    public string $seo_description = '';

    public bool $robots_index = true;

    public bool $robots_follow = true;

    public string $saved = '';

    public function edit(int $id): void
    {
        $page = Page::query()->findOrFail($id);

        $this->editingId = $page->id;
        $this->title = (string) $page->title;
        $this->slug = (string) $page->slug;
        $this->excerpt = (string) $page->excerpt;
        $this->content = (string) $page->content;
        $this->status = (string) $page->status;
        $this->version = (string) $page->version;
        $this->effective_from = $page->effective_from?->format('Y-m-d');
        $this->show_in_footer = (bool) $page->show_in_footer;
        $this->position = (int) $page->position;
        $this->seo_title = (string) $page->seo_title;
        $this->seo_description = (string) $page->seo_description;
        $this->robots_index = (bool) $page->robots_index;
        $this->robots_follow = (bool) $page->robots_follow;
        $this->saved = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/', Rule::unique('pages', 'slug')->ignore($this->editingId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'version' => ['nullable', 'string', 'max:32'],
            'effective_from' => ['nullable', 'date'],
            'position' => ['integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ], [
            'slug.regex' => 'Slugul poate conține doar litere mici, cifre și cratime.',
        ]);

        $page = $this->editingId === null ? new Page : Page::query()->findOrFail($this->editingId);

        $page->fill([
            ...$data,
            'excerpt' => $data['excerpt'] ?: null,
            'content' => $data['content'] ?: null,
            'version' => $data['version'] ?: null,
            'seo_title' => $data['seo_title'] ?: null,
            'seo_description' => $data['seo_description'] ?: null,
            'show_in_footer' => $this->show_in_footer,
            'robots_index' => $this->robots_index,
            'robots_follow' => $this->robots_follow,
        ]);

        // published_at is what the storefront actually filters on, so publishing has to set it
        // and unpublishing has to clear it; leaving a stale timestamp would keep the page live.
        $page->published_at = $data['status'] === 'published' ? ($page->published_at ?? now()) : null;

        $page->save();

        $this->editingId = $page->id;
        $this->saved = 'Pagina a fost salvată.';
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'slug', 'excerpt', 'content', 'status', 'version', 'effective_from', 'seo_title', 'seo_description', 'saved']);
        $this->resetValidation();
        $this->show_in_footer = true;
        $this->robots_index = true;
        $this->robots_follow = true;
        $this->position = 0;
    }

    public function updatedTitle(string $value): void
    {
        // Only ever suggested for a page that does not exist yet: changing a live slug silently
        // would break every link and bookmark pointing at it.
        if ($this->editingId === null && $this->slug === '') {
            $this->slug = Str::slug($value);
        }
    }

    public function render()
    {
        return view('livewire.admin.content.pages-index', [
            'pages' => Page::query()->orderBy('position')->orderBy('title')->get(),
        ]);
    }
}
