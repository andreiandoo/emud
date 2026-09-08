<div class="space-y-8">
    <x-seo :title="$product->name"
           :description="$product->short_description"
           :canonical="route('storefront.product', $product)"
           type="product" />

    <nav class="text-xs text-stone-500">
        <a href="{{ route('storefront.home') }}" class="hover:underline">Acasă</a>
        @php($primaryCategory = $product->categories->first())
        @if($primaryCategory)
            <span class="mx-1">/</span>
            <a href="{{ route('storefront.category', $primaryCategory) }}" class="hover:underline">{{ $primaryCategory->name }}</a>
        @endif
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="space-y-3">
            @php($cover = $product->media->first())
            <div class="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-stone-100">
                @if($cover)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($cover->disk)->url($cover->path) }}"
                         alt="{{ $cover->alt_text ?? $product->name }}" class="h-full w-full object-cover">
                @else
                    <span class="text-sm text-stone-400">Fără imagine</span>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            @if($product->brand)
                <span class="text-sm font-medium uppercase tracking-wide text-stone-500">{{ $product->brand->name }}</span>
            @endif
            <h1 class="text-3xl font-black tracking-tight">{{ $product->name }}</h1>

            <div class="text-sm text-stone-500">
                @if($product->sku)<span>Cod: {{ $product->sku }}</span>@endif
                @if($product->manufacturer_part_number)<span class="ml-3">MPN: {{ $product->manufacturer_part_number }}</span>@endif
            </div>

            <div @class([
                'rounded-xl border p-4',
                'border-lime-300 bg-lime-50' => $verdict->isCertain(),
                'border-amber-300 bg-amber-50' => ! $verdict->isCertain() && $verdict->fits(),
                'border-stone-300 bg-stone-50' => ! $verdict->fits(),
            ])>
                <p class="font-semibold">
                    {{ $verdict->label() }}@if($vehicle) · {{ $vehicle->label() }}@endif
                </p>
                <p class="mt-1 text-sm text-stone-600">{{ $verdict->explanation() }}</p>
                @unless($vehicle)
                    <a href="{{ route('storefront.home') }}" class="mt-2 inline-block text-sm font-semibold underline">Alege-ți mașina</a>
                @endunless
            </div>

            <div class="text-3xl font-black">
                {{ $variant?->retail_price !== null ? \App\Support\Money::of($variant->retail_price, $variant->currency ?? config('emud.catalog.default_currency', 'RON'))->format() : 'Preț la cerere' }}
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <input type="number" min="1" max="99" wire:model="quantity" class="w-20 rounded-lg border-stone-300 text-sm">
                <button wire:click="addToCart" class="rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-stone-700">
                    Adaugă în coș
                </button>
                <button wire:click="toggleWishlist" class="rounded-lg border border-stone-300 px-4 py-3 text-sm font-semibold hover:border-stone-900">
                    Salvează la favorite
                </button>
                @if(session('cart-added'))
                    <span class="text-sm font-semibold text-lime-700">{{ session('cart-added') }}</span>
                @endif
                @if(session('wishlist'))
                    <span class="text-sm font-semibold text-stone-700">{{ session('wishlist') }}</span>
                @endif
            </div>
            @error('quantity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            @unless($verdict->isCertain())
                <p class="text-xs text-stone-500">
                    Compatibilitatea nu este confirmată pentru mașina ta. Verifică înainte de comandă.
                </p>
            @endunless

            @if($product->short_description)
                <p class="text-stone-700">{{ $product->short_description }}</p>
            @endif

            @if($product->warranty_months)
                <p class="text-sm text-stone-600">Garanție: {{ $product->warranty_months }} luni</p>
            @endif
        </div>
    </div>

    @if($product->description)
        <section>
            <h2 class="mb-3 text-lg font-bold">Descriere</h2>
            <div class="prose prose-stone max-w-none text-stone-700">{!! app(\App\Support\HtmlSanitizer::class)->clean($product->description) !!}</div>
        </section>
    @endif

    @if($product->fitments->isNotEmpty())
        <section>
            <h2 class="mb-3 text-lg font-bold">Compatibilitate declarată</h2>
            <ul class="space-y-1 text-sm text-stone-700">
                @foreach($product->fitments as $fitment)
                    <li>
                        {{ $fitment->make?->name ?? 'Orice marcă' }}
                        {{ $fitment->model?->name }}
                        {{ $fitment->generation?->name }}
                        @if($fitment->year_from || $fitment->year_to)
                            <span class="text-stone-500">({{ $fitment->year_from ?? '…' }}–{{ $fitment->year_to ?? '…' }})</span>
                        @endif
                        @if($fitment->requires_modification)
                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">necesită modificări</span>
                        @endif
                        @if($fitment->notes)<span class="text-stone-500">· {{ $fitment->notes }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
