<?php

namespace App\Livewire\Storefront;

use App\Models\CartItem;
use App\Storefront\CartManager;
use App\Support\Money;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The basket that slides in from the side.
 *
 * It opens when something is added and when the header's basket icon is pressed, so the
 * customer sees what they have without leaving the page they were on. The full basket page
 * still exists for everything this panel leaves out.
 */
class CartDrawer extends Component
{
    #[On('cart-changed')]
    public function refreshCart(): void
    {
        // Re-rendering is the point; the lines are read fresh in render().
    }

    public function setQuantity(int $itemId, int $quantity, CartManager $carts): void
    {
        $carts->setQuantity($this->ownedItem($itemId, $carts), $quantity);

        // Aimed at the badge rather than broadcast, so this panel does not re-render itself a
        // second time for a change it has just drawn.
        $this->dispatch('cart-changed')->to(CartBadge::class);
    }

    public function remove(int $itemId, CartManager $carts): void
    {
        $carts->remove($this->ownedItem($itemId, $carts));

        $this->dispatch('cart-changed')->to(CartBadge::class);
    }

    public function render(CartManager $carts)
    {
        $cart = $carts->current();
        $items = $cart?->items()->with(['product.media', 'variant'])->get() ?? collect();
        $currency = (string) ($cart?->currency ?? config('emud.catalog.default_currency', 'RON'));

        return view('livewire.storefront.cart-drawer', [
            'items' => $items,
            'count' => (int) $items->sum('quantity'),
            'lineTotal' => fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity),
            'subtotal' => Money::sum(
                $items->map(fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity)),
                $currency,
            ),
        ]);
    }

    /**
     * Line ids come from the page, so each action re-reads the line through the visitor's own
     * basket; editing an id in the request must not reach someone else's.
     */
    private function ownedItem(int $itemId, CartManager $carts): CartItem
    {
        $cart = $carts->current();

        abort_if($cart === null, 404);

        return $cart->items()->findOrFail($itemId);
    }
}
