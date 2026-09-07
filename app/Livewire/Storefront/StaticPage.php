<?php

namespace App\Livewire\Storefront;

use App\Models\Page;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class StaticPage extends Component
{
    public Page $page;

    /**
     * Resolved here rather than by route binding so an unpublished page is a 404 for visitors
     * instead of a draft that happens to be reachable by guessing its slug.
     */
    public function mount(string $slug): void
    {
        $this->page = Page::query()->published()->where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.static-page');
    }
}
