<?php

namespace App\Livewire\Storefront;

use App\Models\CartItem;
use App\Storefront\CartManager;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::storefront')]
class CartPage extends Component
{
    public function setQuantity(int $itemId, int $quantity, CartManager $carts): void
    {
        $carts->setQuantity($this->ownedItem($itemId, $carts), $quantity);
    }

    public function remove(int $itemId, CartManager $carts): void
    {
        $carts->remove($this->ownedItem($itemId, $carts));
    }

    public function render(CartManager $carts)
    {
        $cart = $carts->current();
        $items = $cart?->items()->with(['product.media', 'variant'])->get() ?? collect();

        $currency = (string) ($cart?->currency ?? config('emud.catalog.default_currency', 'RON'));

        return view('livewire.storefront.cart-page', [
            'items' => $items,
            'currency' => $currency,
            'lineTotal' => fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity),
            'subtotal' => Money::sum(
                $items->map(fn (CartItem $item) => Money::of($item->unit_price, $currency)->times((int) $item->quantity)),
                $currency,
            ),
        ]);
    }

    /**
     * Line ids come from the rendered page, so each action re-reads the line through the
     * visitor's own cart. Without that, editing an id in the request would let anyone change
     * quantities in a basket that is not theirs.
     */
    private function ownedItem(int $itemId, CartManager $carts): CartItem
    {
        $cart = $carts->current();

        abort_if($cart === null, 404);

        return $cart->items()->findOrFail($itemId);
    }
}
