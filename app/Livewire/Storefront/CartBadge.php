<?php

namespace App\Livewire\Storefront;

use App\Storefront\CartManager;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The basket icon in the header, with its count.
 *
 * A component of its own so the number can change without a page load: every place that
 * changes the basket dispatches cart-changed, and before this existed nothing listened, so the
 * badge only caught up on the next navigation.
 */
class CartBadge extends Component
{
    #[On('cart-changed')]
    public function refreshCount(): void
    {
        // Re-rendering is the whole point; the count is read fresh in render().
    }

    public function render(CartManager $carts)
    {
        return view('livewire.storefront.cart-badge', [
            'count' => (int) ($carts->current()?->items()->sum('quantity') ?? 0),
        ]);
    }
}
