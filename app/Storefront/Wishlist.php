<?php

namespace App\Storefront;

use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use App\Storefront\Compatibility\FitmentMatcher;
use Illuminate\Database\Eloquent\Collection;

/**
 * Products a customer has saved, for the account or for one car.
 *
 * The compatibility verdict is recorded at the moment of saving. Recomputing it silently on
 * every view would let a catalogue correction turn a saved part from "fits" into "does not"
 * with no trace, and the customer would never learn that the answer had changed.
 */
class Wishlist
{
    public function __construct(private FitmentMatcher $matcher, private VehicleContext $context) {}

    /** @return Collection<int, WishlistItem> */
    public function forUser(User $user, ?int $vehicleId = null, bool $onlyScope = false): Collection
    {
        return $user->wishlistItems()
            ->with(['product.media', 'product.brand', 'product.fitments', 'product.variants', 'vehicle.make', 'vehicle.model'])
            ->when($onlyScope, fn ($query) => $query->forVehicle($vehicleId))
            ->latest('id')
            ->get();
    }

    /**
     * Saving the same product to the same scope again is not an error: the customer already
     * wants it. The note is refreshed so a second save can update it.
     */
    public function add(User $user, Product $product, ?CustomerVehicle $vehicle = null, ?string $note = null): WishlistItem
    {
        $selected = $vehicle === null
            ? $this->context->current()
            : SelectedVehicle::fromCustomerVehicle($vehicle->loadMissing(['make', 'model', 'generation']));

        return WishlistItem::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'variant_id' => null,
                'customer_vehicle_id' => $vehicle?->id,
            ],
            [
                'verdict_when_saved' => $this->matcher->verdictFor($product->loadMissing('fitments'), $selected),
                'note' => $note,
            ],
        );
    }

    public function remove(User $user, int $itemId): void
    {
        // Scoped to the owner so an id from the page cannot reach into someone else's list.
        $user->wishlistItems()->where('id', $itemId)->delete();
    }

    public function contains(User $user, Product $product, ?int $vehicleId = null): bool
    {
        return $user->wishlistItems()
            ->where('product_id', $product->id)
            ->forVehicle($vehicleId)
            ->exists();
    }

    /**
     * Whether the answer we gave when the item was saved still holds. Reported rather than
     * applied, so the customer sees that something changed instead of finding a different
     * label with no explanation.
     */
    public function verdictChanged(WishlistItem $item): bool
    {
        if ($item->verdict_when_saved === null || $item->vehicle === null) {
            return false;
        }

        $current = $this->matcher->verdictFor(
            $item->product->loadMissing('fitments'),
            SelectedVehicle::fromCustomerVehicle($item->vehicle->loadMissing(['make', 'model', 'generation'])),
        );

        return $current !== $item->verdict_when_saved;
    }
}
