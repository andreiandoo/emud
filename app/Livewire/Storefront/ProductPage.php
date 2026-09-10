<?php

namespace App\Livewire\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Review;
use App\Storefront\Availability;
use App\Storefront\CartManager;
use App\Storefront\Compatibility\CompatibilityVerdict;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use App\Storefront\Wishlist;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

#[Layout('layouts::storefront')]
class ProductPage extends Component
{
    public Product $product;

    /** False only for staff previewing something the public cannot reach yet. */
    public bool $published = true;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        // status is cast to an enum, so comparing it against the raw string would never match
        // and every product would 404.
        $this->published = $product->status === ProductStatus::Active && $product->published_at !== null;

        // Staff can open an unpublished product to see how it will look before releasing it.
        // Everyone else gets the 404 they would have got before.
        abort_unless($this->published || request()->user()?->isAdmin(), 404);

        $this->product = $product->load([
            'brand',
            'media',
            'variants',
            'fitments.make',
            'fitments.model',
            'fitments.generation',
            'categories',
            'collections',
        ]);
    }

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
        $variant = $this->product->variants->firstWhere('is_active', true);
        // Resolved once and handed to both the page and the structured data: they must not be
        // able to disagree about whether the part is in stock.
        $availability = Availability::forProduct($this->product);
        $reviews = $this->reviews();

        return view('livewire.storefront.product-page', [
            'vehicle' => $vehicle,
            'verdict' => $vehicle === null
                ? CompatibilityVerdict::Unknown
                : $matcher->verdictFor($this->product, $vehicle),
            'variant' => $variant,
            'availability' => $availability,
            'gallery' => $this->product->media->where('type', 'image')->values(),
            'downloads' => $this->product->media->where('type', '!=', 'image')->values(),
            'specifications' => $this->specifications(),
            'highlights' => $this->highlights(),
            'addOns' => $this->addOns(),
            'related' => $this->related(),
            'reviews' => $reviews,
            'productJsonLd' => $this->productJsonLd($variant, $availability, $reviews),
        ]);
    }

    /**
     * The spec table, from the attribute values recorded against the product.
     *
     * Variant-level values are skipped: this page shows one product, and mixing the shared
     * specification with one variant's would state a number that is only true for some of what
     * is on sale.
     *
     * @return EloquentCollection<int, ProductAttributeValue>
     */
    private function specifications(): EloquentCollection
    {
        return ProductAttributeValue::query()
            ->with(['attribute', 'option'])
            ->where('product_id', $this->product->id)
            ->whereNull('variant_id')
            ->get()
            ->filter(fn (ProductAttributeValue $value): bool => $value->attribute !== null)
            ->sortBy(fn (ProductAttributeValue $value): string => (string) $value->attribute->name)
            ->values();
    }

    /**
     * Short selling points, when a feed or an operator recorded them.
     *
     * Read out of metadata rather than parsed out of the description: pulling <li> elements from
     * supplier HTML gets a shipping notice as often as it gets a feature.
     *
     * @return list<string>
     */
    private function highlights(): array
    {
        $highlights = $this->product->metadata['highlights'] ?? [];

        if (! is_array($highlights)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $line): string => trim((string) (is_scalar($line) ? $line : '')),
            $highlights,
        )));
    }

    /**
     * Parts that go on the same car but do a different job — the "you will also need this"
     * shelf. Same collections, different category, so a second set of brake pads is not offered
     * next to the first.
     *
     * @return EloquentCollection<int, Product>
     */
    private function addOns(): EloquentCollection
    {
        $collectionIds = $this->product->collections->pluck('id');

        if ($collectionIds->isEmpty()) {
            return new EloquentCollection;
        }

        $categoryIds = $this->product->categories->pluck('id');

        return Product::query()
            ->active()
            ->with(['brand', 'media', 'variants'])
            ->whereKeyNot($this->product->id)
            ->whereHas('collections', fn (Builder $q) => $q->whereIn('vehicle_collections.id', $collectionIds))
            ->when($categoryIds->isNotEmpty(), fn (Builder $q) => $q
                ->whereDoesntHave('categories', fn (Builder $c) => $c->whereIn('categories.id', $categoryIds)))
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit(6)
            ->get();
    }

    /**
     * The same shelf: same category, different product.
     *
     * @return EloquentCollection<int, Product>
     */
    private function related(): EloquentCollection
    {
        $categoryIds = $this->product->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return new EloquentCollection;
        }

        return Product::query()
            ->active()
            ->with(['brand', 'media', 'variants'])
            ->whereKeyNot($this->product->id)
            ->whereHas('categories', fn (Builder $q) => $q->whereIn('categories.id', $categoryIds))
            ->orderByDesc('is_featured')
            ->orderBy('id')
            ->limit(8)
            ->get();
    }

    /** @return EloquentCollection<int, Review> */
    private function reviews(): EloquentCollection
    {
        return Review::query()
            ->published()
            ->where('product_id', $this->product->id)
            ->ordered()
            ->limit(12)
            ->get();
    }

    /**
     * Built here, not with @json in the view: the Blade directive stops at the first balanced
     * closing paren, which cuts a multi-line array literal in half and leaves the compiled view
     * a parse error — a 500 on every request, found only in production.
     *
     * No aggregateRating is emitted unless reviews with a score are actually published. Marking
     * up a rating the page does not show is exactly what the guidelines call misleading.
     */
    private function productJsonLd(mixed $variant, Availability $availability, EloquentCollection $reviews): string
    {
        $rated = $reviews->filter(fn (Review $review): bool => $review->rating !== null);

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->product->name,
            'description' => (string) ($this->product->short_description ?? ''),
            'sku' => $this->product->sku,
            'mpn' => $this->product->manufacturer_part_number,
            'brand' => $this->product->brand === null
                ? null
                : ['@type' => 'Brand', 'name' => $this->product->brand->name],
            'image' => $this->product->media
                ->where('type', 'image')
                ->map(fn ($medium): string => Storage::disk($medium->disk)->url($medium->path))
                ->values()
                ->all(),
        ];

        if ($variant?->retail_price !== null) {
            $payload['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $variant->retail_price,
                'priceCurrency' => $variant->currency ?? config('emud.catalog.default_currency', 'RON'),
                'url' => route('storefront.product', $this->product),
                'availability' => $availability->orderable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
            ];
        }

        if ($rated->isNotEmpty()) {
            $payload['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $rated->avg('rating'), 1),
                'reviewCount' => $rated->count(),
            ];
        }

        return (string) json_encode(
            array_filter($payload, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== ''),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** Formatted price, or null when the product has none to state. */
    public function priceLabel(mixed $variant): ?string
    {
        return $variant?->retail_price === null
            ? null
            : Money::of($variant->retail_price, $variant->currency ?? config('emud.catalog.default_currency', 'RON'))->format();
    }
}
