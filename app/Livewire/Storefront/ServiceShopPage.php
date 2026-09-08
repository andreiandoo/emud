<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceShop;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class ServiceShopPage extends Component
{
    public ServiceShop $shop;

    /** Resolved here so a draft listing is a 404 rather than readable by guessing the slug. */
    public function mount(string $slug): void
    {
        $this->shop = ServiceShop::query()->published()->where('slug', $slug)->firstOrFail();
    }

    public function render()
    {
        return view('livewire.storefront.service-shop-page');
    }
}
