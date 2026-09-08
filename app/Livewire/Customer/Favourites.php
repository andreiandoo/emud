<?php

namespace App\Livewire\Customer;

use App\Models\WishlistItem;
use App\Storefront\Wishlist;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::storefront')]
class Favourites extends Component
{
    /** Which garage vehicle the list is scoped to; empty means the account-wide list. */
    #[Url(except: '')]
    public string $vehicle = '';

    public function remove(int $itemId, Wishlist $wishlist): void
    {
        $wishlist->remove(auth()->user(), $itemId);
    }

    public function moveToVehicle(int $itemId, ?int $vehicleId): void
    {
        $item = auth()->user()->wishlistItems()->findOrFail($itemId);

        // The uniqueness index would reject a move onto a scope that already holds the product,
        // so the duplicate is dropped instead of surfacing a constraint error to the customer.
        $alreadyThere = auth()->user()->wishlistItems()
            ->where('product_id', $item->product_id)
            ->forVehicle($vehicleId)
            ->whereKeyNot($item->id)
            ->exists();

        if ($alreadyThere) {
            $item->delete();

            return;
        }

        $item->update(['customer_vehicle_id' => $vehicleId]);
    }

    public function render(Wishlist $wishlist)
    {
        $user = auth()->user();
        // An empty selection means the account-wide list, which is its own scope rather than
        // "everything": a product saved for one car belongs to that car's list, not to both.
        $vehicleId = $this->vehicle === '' ? null : (int) $this->vehicle;

        return view('livewire.customer.favourites', [
            'items' => $wishlist->forUser($user, $vehicleId, onlyScope: true),
            'vehicles' => $user->vehicles()->with(['make', 'model'])->orderByDesc('is_primary')->orderBy('id')->get(),
            'changed' => fn (WishlistItem $item) => $wishlist->verdictChanged($item),
        ]);
    }
}
