<?php

namespace App\Livewire\Storefront;

use App\Directory\NearbyShops;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceShop;
use App\Models\ShippingMethod;
use App\Storefront\Availability;
use App\Storefront\CartManager;
use App\Storefront\Compatibility\CompatibilityVerdict;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\VehicleContext;
use App\Storefront\Wishlist;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Laid out full width so the "goes with this" shelf can run edge to edge on its own ground;
 * every other section wraps itself in .shell.
 */
#[Layout('layouts::storefront', ['fullWidth' => true])]
class ProductPage extends Component
{
    public Product $product;

    /** False only for staff previewing something the public cannot reach yet. */
    public bool $published = true;

    public int $quantity = 1;

    /** Set once the customer asks for workshops, so a page that is only read never pays for the lookup. */
    public bool $findingShops = false;

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

    public function findShops(): void
    {
        $this->findingShops = true;
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
        $this->dispatch('cart-added');
        session()->flash('cart-added', 'Produsul a fost adăugat în coș.');
    }

    public function render(VehicleContext $context, FitmentMatcher $matcher, Wishlist $wishlist, NearbyShops $nearby)
    {
        // With several cars chosen, the page speaks about the one the part suits best: a part for
        // the weekend 4x4 should not read as wrong because the family car comes first.
        $selection = $context->selection();
        [$vehicle, $verdict] = $selection === null
            ? [null, CompatibilityVerdict::Unknown]
            : $matcher->bestFit($this->product, $selection);
        $variant = $this->product->variants->firstWhere('is_active', true);
        // Resolved once and handed to both the page and the structured data: they must not be
        // able to disagree about whether the part is in stock.
        $availability = Availability::forProduct($this->product);
        $reviews = $this->reviews();
        $trail = $this->trail();
        $currency = (string) ($variant?->currency ?? config('emud.catalog.default_currency', 'RON'));
        $shipping = ShippingMethod::query()->where('is_active', true)->orderBy('base_price')->first();
        $user = auth()->user();
        $location = $user === null ? ['city' => null, 'county' => null] : $nearby->locationOf($user);
        $service = $this->mountService($trail);

        return view('livewire.storefront.product-page', [
            'vehicle' => $vehicle,
            'verdict' => $verdict,
            'carCount' => $selection === null ? 0 : count($selection),
            'variant' => $variant,
            'availability' => $availability,
            // The cheapest way the shop delivers, at this part's price: what delivery costs if
            // this is all the customer orders.
            'shippingPrice' => $shipping?->priceFor(Money::of($variant?->retail_price ?? 0, $currency)),
            'freeOver' => $shipping?->free_over === null ? null : Money::of($shipping->free_over, $currency),
            'inWishlist' => $user !== null && $wishlist->contains($user, $this->product, $this->activeVehicleId()),
            'mountService' => $service,
            'mountLocation' => $location,
            'mountShops' => $this->findingShops ? $this->mountShops($service, $location) : null,
            'shopsUrl' => route('storefront.services', array_filter(['county' => $location['county'], 'service' => $service?->slug])),
            'gallery' => $this->product->media->where('type', 'image')->values(),
            'downloads' => $this->product->media->where('type', '!=', 'image')->values(),
            'specifications' => $this->specifications(),
            'highlights' => $this->highlights(),
            'addOns' => $this->addOns(),
            'related' => $this->related(),
            'reviews' => $reviews,
            'trail' => $trail,
            'productJsonLd' => $this->productJsonLd($variant, $availability, $reviews),
        ]);
    }

    /**
     * The deepest category the product is filed under and every category above it: the path a
     * customer would have walked down to get here, for the breadcrumb.
     *
     * @return Collection<int, Category>
     */
    private function trail(): Collection
    {
        $trail = collect();
        $current = $this->product->categories->sortByDesc('depth')->first();

        // Bounded, so a cycle an import once wrote cannot hold the page.
        while ($current !== null && $trail->count() < 8) {
            $trail->prepend($current);
            $current = $current->parent_id ? Category::query()->find($current->parent_id) : null;
        }

        return $trail;
    }

    /**
     * The workshop job this part needs, read off the parts category the job catalogue links it
     * to. The deepest category wins: "Amortizoare" says more than "Suspensie".
     *
     * @param  Collection<int, Category>  $trail
     */
    private function mountService(Collection $trail): ?Service
    {
        $ids = $trail->pluck('id')->values();

        if ($ids->isEmpty()) {
            return null;
        }

        return Service::query()
            ->active()
            ->whereIn('category_id', $ids)
            ->get()
            ->sortByDesc(fn (Service $service): int => (int) $ids->search($service->category_id))
            ->first();
    }

    /**
     * Workshops that do the job, near the customer when we know where that is. When nobody near
     * lists the job, the nearest workshops come next, then the job anywhere: a short list with
     * something in it is more use than an empty dialog.
     *
     * @param  array{city: ?string, county: ?string}  $location
     * @return array{shops: EloquentCollection<int, ServiceShop>, byService: bool}
     */
    private function mountShops(?Service $service, array $location): array
    {
        $citySlug = $location['city'] === null ? null : Str::slug($location['city']);
        $county = $location['county'];
        $known = $citySlug !== null || $county !== null;

        $query = fn (bool $byService, bool $nearby): Builder => ServiceShop::query()
            ->published()
            ->with('hours')
            ->when($byService, fn (Builder $q) => $q->whereHas('services', fn (Builder $job) => $job->whereKey($service?->id)))
            ->when($nearby, fn (Builder $q) => $q->where(fn (Builder $near) => $near
                ->when($citySlug !== null, fn (Builder $inner) => $inner->orWhere('city_slug', $citySlug))
                ->when($county !== null, fn (Builder $inner) => $inner->orWhereRaw('lower(county) = ?', [mb_strtolower((string) $county)]))))
            ->when($nearby && $citySlug !== null, fn (Builder $q) => $q->orderByRaw('case when city_slug = ? then 0 else 1 end', [$citySlug]))
            ->promotedFirst()
            ->orderByDesc('fits_parts_bought_here')
            ->orderBy('name')
            ->limit(6);

        $attempts = $known ? [[true, true], [false, true], [true, false]] : [[true, false], [false, false]];

        foreach ($attempts as [$byService, $nearby]) {
            if ($byService && $service === null) {
                continue;
            }

            $shops = $query($byService, $nearby)->get();

            if ($shops->isNotEmpty()) {
                return ['shops' => $shops, 'byService' => $byService];
            }
        }

        return ['shops' => new EloquentCollection, 'byService' => false];
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
            ->withAvailability()
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
     * The same shelf: same category, different product — the alternatives.
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
            ->withAvailability()
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
