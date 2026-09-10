@php($settings = app(\App\Settings\StoreSettings::class))
@php($methodLabels = \App\Livewire\Admin\Settings\FooterSettings::PAYMENT_METHODS)
@php($payments = collect($settings->array('footer_payment_methods')))
@php($currency = $variant?->currency ?? config('emud.catalog.default_currency', 'RON'))
@php($price = $variant?->retail_price !== null ? \App\Support\Money::of($variant->retail_price, $currency)->format() : null)
@php($compareAt = $variant?->compare_at_price !== null && $variant->compare_at_price > $variant->retail_price
        ? \App\Support\Money::of($variant->compare_at_price, $currency)->format() : null)
@php($primaryCategory = $product->categories->first())

<div class="space-y-14 pt-8">
    @unless($published)
        {{-- The gutter lives on a wrapper, not on this element: .shell sits in the components
             layer and any padding utility written alongside it would win and cancel it. --}}
        <div class="shell">
            <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                Previzualizare: produsul nu este publicat, deci clienții nu îl pot vedea încă.
            </p>
        </div>
    @endunless

    <x-seo :title="$product->name"
           :description="$product->short_description"
           :canonical="route('storefront.product', $product)"
           :image="$gallery->first() ? \Illuminate\Support\Facades\Storage::disk($gallery->first()->disk)->url($gallery->first()->path) : null"
           type="product" />

    @push('meta')
        <script type="application/ld+json">{!! $productJsonLd !!}</script>
    @endpush

    {{-- Breadcrumb and product wrapped together: the page rhythm is 3.5rem between sections, and
         a crumb trail floating that far above the thing it describes reads as orphaned. --}}
    <div class="space-y-5">
        <nav class="shell text-xs text-stone-500">
            <a href="{{ route('storefront.home') }}" class="hover:underline">Acasă</a>
            @if($primaryCategory)
                <span class="mx-1">/</span>
                <a href="{{ route('storefront.category', $primaryCategory) }}" class="hover:underline">{{ $primaryCategory->name }}</a>
            @endif
            <span class="mx-1">/</span>
            <span class="text-stone-900">{{ $product->name }}</span>
        </nav>

        {{-- The photograph is deliberately the smaller half. On a part like a snorkel or a lift kit
             the picture settles almost nothing; the price, the stock and above all whether it fits
             are what the visitor came for, so those get the room. --}}
        <div class="shell grid gap-10 lg:grid-cols-[20rem_minmax(0,1fr)] xl:grid-cols-[24rem_minmax(0,1fr)]">
            {{-- Gallery. Entirely in Alpine: swapping a photo is a local decision and does not need a
                 round trip to the server to be correct. --}}
            <div x-data="{ active: 0, count: {{ max(1, $gallery->count()) }} }" class="space-y-3">
                <div class="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-stone-100">
                    @forelse($gallery as $index => $medium)
                        {{-- x-cloak on everything but the first: x-show only takes effect once Alpine
                             boots, so without it the whole gallery flashes stacked on a slow load. --}}
                        <img x-show="active === {{ $index }}" @if($index > 0) x-cloak @endif
                             src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                             alt="{{ $medium->alt_text ?? $product->name }}"
                             @if($index > 0) loading="lazy" @endif
                             class="h-full w-full object-contain">
                    @empty
                        <span class="text-sm text-stone-400">Fără imagine</span>
                    @endforelse

                    @if($gallery->count() > 1)
                        <button type="button" @click="active = (active - 1 + count) % count"
                                class="absolute left-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow transition hover:bg-white"
                                aria-label="Imaginea anterioară">
                            <x-storefront.icon name="chevron-right" class="h-4 w-4 rotate-180" />
                        </button>
                        <button type="button" @click="active = (active + 1) % count"
                                class="absolute right-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow transition hover:bg-white"
                                aria-label="Imaginea următoare">
                            <x-storefront.icon name="chevron-right" class="h-4 w-4" />
                        </button>
                    @endif
                </div>

                @if($gallery->count() > 1)
                    <ul class="flex flex-wrap gap-2">
                        @foreach($gallery as $index => $medium)
                            <li>
                                <button type="button" @click="active = {{ $index }}"
                                        :class="active === {{ $index }} ? 'border-stone-900' : 'border-stone-200 hover:border-stone-400'"
                                        class="block h-16 w-16 overflow-hidden rounded-lg border-2 transition"
                                        aria-label="Imaginea {{ $index + 1 }}">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                                         alt="" loading="lazy" class="h-full w-full object-cover">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Buy box. Sticky on desktop so the price and the button stay reachable while the
                 customer reads a specification list that can run to forty rows. --}}
            <div class="space-y-5 lg:sticky lg:top-6 lg:max-w-2xl lg:self-start">
                @if($product->brand)
                    <a href="{{ route('storefront.search', ['q' => $product->brand->name]) }}"
                       class="text-sm font-semibold uppercase tracking-wide text-stone-500 hover:text-stone-900">
                        {{ $product->brand->name }}
                    </a>
                @endif

                <h1 class="text-2xl font-black leading-tight tracking-tight sm:text-3xl">{{ $product->name }}</h1>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                    @if($product->sku)<span>Cod: <span class="font-medium text-stone-700">{{ $product->sku }}</span></span>@endif
                    @if($product->manufacturer_part_number)<span>MPN: <span class="font-medium text-stone-700">{{ $product->manufacturer_part_number }}</span></span>@endif
                </div>

                <div class="flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-black">{{ $price ?? 'Preț la cerere' }}</span>
                    @if($compareAt)
                        <span class="text-lg text-stone-400 line-through">{{ $compareAt }}</span>
                    @endif
                </div>

                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span @class([
                        'font-semibold',
                        'text-emerald-700' => $availability->tone() === 'positive',
                        'text-amber-700' => $availability->tone() === 'warning',
                        'text-red-700' => $availability->tone() === 'danger',
                        'text-stone-500' => $availability->tone() === 'neutral',
                    ])>{{ $availability->label() }}</span>
                    @if($availability->dispatchWindow())
                        <span class="text-stone-500">{{ $availability->dispatchWindow() }}</span>
                    @endif
                </p>

                {{-- Compatibility sits above the button on purpose. It is the one thing that decides
                     whether this part is the right part, and below the fold it would be read after
                     the order rather than before it. --}}
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

                <div class="space-y-3">
                    <div class="flex items-stretch gap-3">
                        <label class="shrink-0">
                            <span class="sr-only">Cantitate</span>
                            <input type="number" min="1" max="99" wire:model="quantity" class="w-20 text-center">
                        </label>
                        <button wire:click="addToCart" @disabled(! $availability->orderable())
                                class="flex-1 rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-stone-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="addToCart">Adaugă în coș</span>
                            <span wire:loading wire:target="addToCart">Se adaugă…</span>
                        </button>
                    </div>

                    <button wire:click="toggleWishlist"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-stone-300 px-4 py-2.5 text-sm font-semibold transition hover:border-stone-900">
                        <x-storefront.icon name="heart" class="h-4 w-4" /> Salvează la favorite
                    </button>

                    @error('quantity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @if(session('cart-added'))
                        <p class="text-sm font-semibold text-lime-700">{{ session('cart-added') }}</p>
                    @endif
                    @if(session('wishlist'))
                        <p class="text-sm font-semibold text-stone-700">{{ session('wishlist') }}</p>
                    @endif
                </div>

                <ul class="space-y-2 border-t border-stone-200 pt-4 text-sm text-stone-600">
                    <li class="flex items-center gap-2">
                        <x-storefront.icon name="truck" class="h-4 w-4 shrink-0 text-stone-400" /> Livrare prin curier în toată țara
                    </li>
                    @if($product->warranty_months)
                        <li class="flex items-center gap-2">
                            <x-storefront.icon name="check" class="h-4 w-4 shrink-0 text-stone-400" /> Garanție {{ $product->warranty_months }} luni
                        </li>
                    @endif
                    <li class="flex items-center gap-2">
                        <x-storefront.icon name="tool" class="h-4 w-4 shrink-0 text-stone-400" />
                        <a href="{{ route('storefront.services') }}" class="underline underline-offset-2 hover:text-stone-900">Găsește un service care îl montează</a>
                    </li>
                </ul>

                @if($payments->isNotEmpty())
                    <ul class="flex flex-wrap gap-2 border-t border-stone-200 pt-4">
                        @foreach($payments as $method)
                            @if(isset($methodLabels[$method]))
                                <li class="rounded border border-stone-200 px-2 py-1 text-[11px] font-semibold text-stone-500">{{ $methodLabels[$method] }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if($addOns->isNotEmpty())
        {{-- Its own ground, edge to edge, one row. A grid here competed with the product grid
             further down the page and read as "more results" rather than "you will need these
             too"; a single rail cannot be mistaken for a listing. --}}
        <section class="border-y border-stone-200 bg-white py-10">
            <div class="shell">
                <h2 class="text-xl font-bold">Se montează cu</h2>
                <p class="mt-1 text-sm text-stone-600">Alte piese care merg pe aceleași mașini, din alte categorii.</p>

                <ul class="-mx-4 mt-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0
                           [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach($addOns as $addOn)
                        <li class="w-56 shrink-0 snap-start sm:w-64">
                            @include('livewire.storefront.partials.product-card', ['product' => $addOn, 'verdict' => null])
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if($product->short_description || $product->description || $highlights !== [])
        <section class="shell grid gap-8 lg:grid-cols-[1fr_20rem]">
            <div>
                <h2 class="mb-3 text-xl font-bold">Descriere</h2>
                @if($product->short_description)
                    <p class="mb-4 text-lg leading-relaxed text-stone-700">{{ $product->short_description }}</p>
                @endif
                @if($product->description)
                    <div class="prose prose-stone max-w-none text-stone-700">
                        {!! app(\App\Support\HtmlSanitizer::class)->clean($product->description) !!}
                    </div>
                @endif
            </div>

            @if($highlights !== [])
                <aside class="h-fit rounded-2xl bg-stone-100 p-6">
                    <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-stone-500">Pe scurt</h3>
                    <ul class="space-y-2 text-sm text-stone-700">
                        @foreach($highlights as $highlight)
                            <li class="flex gap-2">
                                <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-stone-500" />
                                <span>{{ $highlight }}</span>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            @endif
        </section>
    @endif

    @if($specifications->isNotEmpty())
        <section class="shell">
            <h2 class="mb-4 text-xl font-bold">Specificații</h2>
            <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($specifications as $specification)
                            @php($value = $specification->displayValue())
                            @if($value !== null)
                                <tr class="border-b border-stone-100 last:border-b-0">
                                    <th scope="row" class="w-1/2 px-4 py-3 text-left font-normal text-stone-500">{{ $specification->attribute->name }}</th>
                                    <td class="px-4 py-3 font-medium text-stone-900">{{ $value }}</td>
                                </tr>
                            @endif
                        @endforeach
                        @if($product->weight_kg)
                            <tr class="border-b border-stone-100 last:border-b-0">
                                <th scope="row" class="px-4 py-3 text-left font-normal text-stone-500">Greutate</th>
                                <td class="px-4 py-3 font-medium text-stone-900">{{ rtrim(rtrim((string) $product->weight_kg, '0'), '.') }} kg</td>
                            </tr>
                        @endif
                        @if(is_array($product->dimensions_cm) && $product->dimensions_cm !== [])
                            <tr class="border-b border-stone-100 last:border-b-0">
                                <th scope="row" class="px-4 py-3 text-left font-normal text-stone-500">Dimensiuni ambalaj</th>
                                <td class="px-4 py-3 font-medium text-stone-900">
                                    {{ collect($product->dimensions_cm)->filter()->implode(' × ') }} cm
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="shell">
        <h2 class="mb-1 text-xl font-bold">Compatibilitate</h2>

        @if($product->is_universal)
            <p class="text-stone-600">Produs universal — nu depinde de modelul mașinii.</p>
        @elseif($product->fitments->isEmpty())
            <p class="text-stone-600">
                Nu avem încă lista de compatibilitate pentru acest produs.
                <a href="{{ route('storefront.contact') }}" class="font-semibold underline">Întreabă-ne</a> înainte de comandă.
            </p>
        @else
            <p class="mb-4 text-sm text-stone-600">Mașinile pentru care producătorul sau furnizorul declară potrivire.</p>
            <ul class="grid gap-x-6 gap-y-2 text-sm text-stone-700 sm:grid-cols-2">
                @foreach($product->fitments as $fitment)
                    <li class="flex flex-wrap items-baseline gap-x-2 border-b border-stone-100 pb-2">
                        <span class="font-medium text-stone-900">
                            {{ $fitment->make?->name ?? 'Orice marcă' }} {{ $fitment->model?->name }}
                        </span>
                        @if($fitment->generation?->name)<span class="text-stone-500">{{ $fitment->generation->name }}</span>@endif
                        @if($fitment->year_from || $fitment->year_to)
                            <span class="text-stone-500">{{ $fitment->year_from ?? '…' }}–{{ $fitment->year_to ?? '…' }}</span>
                        @endif
                        @if($fitment->requires_modification)
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">necesită modificări</span>
                        @endif
                        @if($fitment->notes)<span class="w-full text-xs text-stone-500">{{ $fitment->notes }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if($product->collections->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach($product->collections as $collection)
                    <a href="{{ $collection->url() }}"
                       class="rounded-full bg-stone-100 px-3 py-1 text-sm font-semibold text-stone-700 transition hover:bg-stone-200">
                        Tot pentru {{ $collection->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($downloads->isNotEmpty())
        <section class="shell">
            <h2 class="mb-4 text-xl font-bold">Documente</h2>
            <ul class="space-y-2">
                @foreach($downloads as $download)
                    <li>
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk($download->disk)->url($download->path) }}"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-stone-800 underline underline-offset-4 hover:text-stone-950">
                            {{ $download->alt_text ?: 'Document' }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($reviews->isNotEmpty())
        <section class="shell">
            <h2 class="mb-1 text-xl font-bold">Ce spun clienții</h2>
            {{-- Said plainly because the rules on review transparency require it: these are not
                 anonymous submissions, and the page has to say where they came from. --}}
            <p class="mb-4 text-sm text-stone-500">
                Recenzii primite de la clienți care au cumpărat produsul și publicate de noi.
            </p>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($reviews as $review)
                    <x-storefront.review-card :review="$review" />
                @endforeach
            </div>
        </section>
    @endif

    @if($related->isNotEmpty())
        <section class="shell">
            <h2 class="mb-4 text-xl font-bold">Ai putea avea nevoie și de</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($related as $relatedProduct)
                    @include('livewire.storefront.partials.product-card', ['product' => $relatedProduct, 'verdict' => null])
                @endforeach
            </div>
        </section>
    @endif
</div>
