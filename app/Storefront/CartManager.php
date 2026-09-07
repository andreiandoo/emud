<?php

namespace App\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The customer's basket.
 *
 * Prices are always read from the database at the moment of adding and stored on the line.
 * Nothing that arrives from the browser is trusted as a price, and the snapshot means a later
 * catalogue edit cannot silently change what the customer thought they were buying — the
 * difference becomes visible at checkout instead.
 */
class CartManager
{
    private const SESSION_KEY = 'storefront.cart_token';

    public function current(bool $create = false): ?Cart
    {
        $cart = $this->existing();

        if ($cart !== null || ! $create) {
            return $cart;
        }

        $cart = Cart::create([
            'token' => (string) Str::uuid(),
            'user_id' => Auth::id(),
            'status' => 'active',
            'currency' => config('emud.catalog.default_currency', 'RON'),
        ]);

        Session::put(self::SESSION_KEY, $cart->token);

        return $cart;
    }

    public function add(Product $product, ?ProductVariant $variant, int $quantity = 1): CartItem
    {
        $quantity = max(1, $quantity);
        $variant ??= $product->variants()->where('is_active', true)->orderBy('position')->first();

        if ($variant === null || $variant->retail_price === null) {
            throw new RuntimeException('Produsul nu are un preț disponibil.');
        }

        if ((int) $variant->product_id !== (int) $product->id) {
            throw new RuntimeException('Varianta nu aparține acestui produs.');
        }

        $cart = $this->current(create: true);

        // The unique index makes concurrent adds of the same line collide rather than duplicate,
        // so the increment happens inside a transaction on a locked row.
        return DB::transaction(function () use ($cart, $product, $variant, $quantity): CartItem {
            $existing = $cart->items()
                ->where('product_id', $product->id)
                ->where('variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->increment('quantity', $quantity);

                return $existing->refresh();
            }

            return $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $variant->retail_price,
                'snapshot' => [
                    'name' => $product->name,
                    'sku' => $variant->sku ?? $product->sku,
                    'brand' => $product->brand?->name,
                    'tax_rate' => (float) config('emud.catalog.default_vat_rate', 21),
                ],
            ]);
        });
    }

    public function setQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $this->remove($item);

            return;
        }

        $item->update(['quantity' => $quantity]);
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Called when a customer signs in. Their basket from before signing in has to survive, and
     * an account that already had one must not lose it either, so lines are merged rather than
     * one cart replacing the other.
     */
    public function mergeInto(User $user): void
    {
        $guestCart = $this->existing();

        if ($guestCart === null || $guestCart->user_id === $user->id) {
            $guestCart?->update(['user_id' => $user->id]);

            return;
        }

        $userCart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($userCart === null) {
            $guestCart->update(['user_id' => $user->id]);

            return;
        }

        DB::transaction(function () use ($guestCart, $userCart): void {
            foreach ($guestCart->items()->get() as $item) {
                $existing = $userCart->items()
                    ->where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->first();

                if ($existing !== null) {
                    $existing->increment('quantity', $item->quantity);
                } else {
                    $item->update(['cart_id' => $userCart->id]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->update(['status' => 'merged']);
        });

        Session::put(self::SESSION_KEY, $userCart->token);
    }

    private function existing(): ?Cart
    {
        $token = Session::get(self::SESSION_KEY);

        $cart = $token === null
            ? null
            : Cart::query()->where('token', $token)->where('status', 'active')->first();

        if ($cart !== null) {
            return $cart;
        }

        // A signed-in customer who arrives with a fresh session still owns their basket.
        if (Auth::check()) {
            $cart = Cart::query()
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($cart !== null) {
                Session::put(self::SESSION_KEY, $cart->token);
            }
        }

        return $cart;
    }
}
