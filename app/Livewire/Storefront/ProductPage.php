<?php

namespace App\Livewire\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Storefront\CartManager;
use App\Storefront\Compatibility\CompatibilityVerdict;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use App\Storefront\Wishlist;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::storefront')]
class ProductPage extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        // status is cast to an enum, so comparing it against the raw string would never match
        // and every product would 404.
        abort_unless($product->status === ProductStatus::Active && $product->published_at !== null, 404);

        $this->product = $product->load(['brand', 'media', 'variants', 'fitments.make', 'fitments.model', 'fitments.generation', 'categories']);
    }

    public int $quantity = 1;

    #[On('vehicle-changed')]
    public function vehicleChanged(): void
    {
        // Re-renders so the compatibility statement follows the newly selected vehicle.
    }

    public function toggleWishlist(Wishlist $wishlist): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirectRoute('customer.login');

            return;
        }

        $vehicle = $user->vehicles()->find($this->activeVehicleId());

        if ($wishlist->contains($user, $this->product, $vehicle?->id)) {
            $existing = $user->wishlistItems()
                ->where('product_id', $this->product->id)
                ->forVehicle($vehicle?->id)
                ->first();

            $wishlist->remove($user, (int) $existing?->id);
            session()->flash('wishlist', 'Produsul a fost scos din favorite.');

            return;
        }

        $wishlist->add($user, $this->product, $vehicle);

        session()->flash('wishlist', $vehicle === null
            ? 'Produsul a fost salvat în favorite.'
            : 'Produsul a fost salvat pentru '.($vehicle->nickname ?: $vehicle->label()).'.');
    }

    /**
     * Saving lands on the active car's list when the customer is shopping for one of their own
     * vehicles, and on the account list otherwise. A vehicle chosen ad hoc in the picker is not
     * in the garage, so it has no list to save to.
     */
    private function activeVehicleId(): ?int
    {
        return app(VehicleContext::class)->current()?->customerVehicleId;
    }

    public function addToCart(CartManager $carts): void
    {
        $this->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        try {
            $carts->add($this->product, null, $this->quantity);
        } catch (RuntimeException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        }

        $this->dispatch('cart-changed');
        session()->flash('cart-added', 'Produsul a fost adăugat în coș.');
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher)
    {
        $vehicle = $context->current();

        return view('livewire.storefront.product-page', [
            'vehicle' => $vehicle,
            'verdict' => $vehicle === null
                ? CompatibilityVerdict::Unknown
                : $matcher->verdictFor($this->product, $vehicle),
            'variant' => $this->product->variants->firstWhere('is_active', true),
        ]);
    }
}
