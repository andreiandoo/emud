@php($settings = app(\App\Settings\StoreSettings::class))
@php($methodLabels = \App\Livewire\Admin\Settings\FooterSettings::PAYMENT_METHODS)
@php($payments = collect($settings->array('footer_payment_methods')))
@php($currency = $variant?->currency ?? config('emud.catalog.default_currency', 'RON'))
@php($price = $variant?->retail_price !== null ? \App\Support\Money::of($variant->retail_price, $currency)->format() : null)
@php($compareAt = $variant?->compare_at_price !== null && $variant->compare_at_price > $variant->retail_price
        ? \App\Support\Money::of($variant->compare_at_price, $currency)->format() : null)
@php($primaryCategory = $product->categories->first())

<div class="pb-8">
    <x-seo :title="$product->name"
           :description="$product->short_description"
           :canonical="route('storefront.product', $product)"
           :image="$gallery->first() ? \Illuminate\Support\Facades\Storage::disk($gallery->first()->disk)->url($gallery->first()->path) : null"
           type="product" />

    @push('meta')
        <script type="application/ld+json">{!! $productJsonLd !!}</script>
    @endpush

    @unless($published)
        <div class="bg-amber-100 text-amber-900">
            <p class="shell py-3 text-sm font-medium">
                Previzualizare: produsul nu este publicat, deci clienții nu îl pot vedea încă.
            </p>
        </div>
    @endunless

    <div class="shell pt-8 sm:pt-10">
        <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-ink2" aria-label="Breadcrumb">
            <a href="{{ route('storefront.home') }}" class="transition hover:text-ink">Acasă</a>
            @if($primaryCategory)
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.category', $primaryCategory) }}" class="transition hover:text-ink">{{ $primaryCategory->name }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span class="text-ink">{{ $product->name }}</span>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-14">
            {{-- Gallery. Entirely in Alpine: swapping a photo is a local decision and does not need a
                 round trip to the server to be correct. --}}
            <div x-data="{ active: 0, count: {{ max(1, $gallery->count()) }} }" class="grid content-start gap-3">
                <div class="relative grid aspect-square place-items-center overflow-hidden rounded-[3px] border border-line bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)]">
                    @forelse($gallery as $index => $medium)
                        {{-- x-cloak on everything but the first: x-show only takes effect once Alpine
                             boots, so without it the whole gallery flashes stacked on a slow load. --}}
                        <img x-show="active === {{ $index }}" @if($index > 0) x-cloak @endif
                             x-transition.opacity.duration.300ms
                             src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                             alt="{{ $medium->alt_text ?? $product->name }}"
                             @if($index > 0) loading="lazy" @endif
                             class="absolute inset-0 h-full w-full object-contain p-8 mix-blend-multiply sm:p-12">
                    @empty
                        <span class="grid place-items-center gap-3 text-sm text-ink2">
                            <x-storefront.icon name="part" class="h-16 w-16 text-line2" />
                            Fără imagine
                        </span>
                    @endforelse

                    @if($gallery->count() > 1)
                        <button type="button" @click="active = (active - 1 + count) % count"
                                class="absolute left-4 grid h-11 w-11 place-items-center rounded-full border border-line2 bg-white/90 transition hover:border-ink"
                                aria-label="Imaginea anterioară">
                            <x-storefront.icon name="arrow-left" class="h-4 w-4" />
                        </button>
                        <button type="button" @click="active = (active + 1) % count"
                                class="absolute right-4 grid h-11 w-11 place-items-center rounded-full border border-line2 bg-white/90 transition hover:border-ink"
                                aria-label="Imaginea următoare">
                            <x-storefront.icon name="arrow-right" class="h-4 w-4" />
                        </button>

                        <span class="absolute bottom-4 right-4 rounded-full bg-white/90 px-3 py-1 font-mono text-[11px] tabular-nums text-ink2">
                            <span x-text="active + 1">1</span> / {{ $gallery->count() }}
                        </span>
                    @endif
                </div>

                @if($gallery->count() > 1)
                    <ul class="flex flex-wrap gap-2">
                        @foreach($gallery as $index => $medium)
                            <li>
                                <button type="button" @click="active = {{ $index }}"
                                        :class="active === {{ $index }} ? 'border-ink' : 'border-line hover:border-line2'"
                                        class="block h-20 w-20 overflow-hidden rounded-[3px] border-2 bg-white transition"
                                        aria-label="Imaginea {{ $index + 1 }}">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path) }}"
                                         alt="" loading="lazy" class="h-full w-full object-contain p-1.5 mix-blend-multiply">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Buy box. Sticky on desktop so the price and the button stay reachable while the
                 customer reads a specification list that can run to forty rows. --}}
            <div class="grid content-start gap-6 lg:sticky lg:top-[calc(var(--st-header-h)+1.5rem)] lg:self-start">
                <div class="grid gap-3">
                    @if($product->brand)
                        <a href="{{ route('storefront.search', ['q' => $product->brand->name]) }}"
                           class="w-fit font-mono text-xs uppercase tracking-[.12em] text-ink2 transition hover:text-signal">
                            {{ $product->brand->name }}
                        </a>
                    @endif

                    <h1 class="st-display text-[clamp(2rem,3.4vw,3.25rem)] leading-[1.02]">{{ $product->name }}</h1>

                    <div class="flex flex-wrap gap-x-5 gap-y-1 font-mono text-xs text-ink2">
                        @if($product->sku)<span>Cod: <span class="text-ink">{{ $product->sku }}</span></span>@endif
                        @if($product->manufacturer_part_number)<span>MPN: <span class="text-ink">{{ $product->manufacturer_part_number }}</span></span>@endif
                    </div>
                </div>

                <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2 border-y border-line py-5">
                    <span class="font-display text-[clamp(2.2rem,3.4vw,3rem)] font-semibold leading-none tracking-[-.02em] tabular-nums">{{ $price ?? 'Preț la cerere' }}</span>
                    @if($compareAt)
                        <span class="text-lg text-ink2 line-through">{{ $compareAt }}</span>
                    @endif

                    <p class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span @class([
                            'inline-flex items-center gap-2 font-semibold',
                            'text-fit' => $availability->tone() === 'positive',
                            'text-amber-700' => $availability->tone() === 'warning',
                            'text-red-700' => $availability->tone() === 'danger',
                            'text-ink2' => $availability->tone() === 'neutral',
                        ])>
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                            {{ $availability->label() }}
                        </span>
                        @if($availability->dispatchWindow())
                            <span class="text-ink2">{{ $availability->dispatchWindow() }}</span>
                        @endif
                    </p>
                </div>

                {{-- Compatibility sits above the button on purpose. It is the one thing that decides
                     whether this part is the right part, and below the fold it would be read after
                     the order rather than before it. --}}
                <div @class([
                    'grid gap-1.5 rounded-[3px] border p-4',
                    'border-fit/35 bg-fit/[.06]' => $verdict->isCertain(),
                    'border-sand2 bg-sand/60' => ! $verdict->isCertain() && $verdict->fits(),
                    'border-line2 bg-white' => ! $verdict->fits(),
                ])>
                    <p class="flex items-center gap-2 font-semibold">
                        <x-storefront.icon :name="$verdict->fits() ? 'check' : 'car'" class="h-4 w-4 shrink-0 {{ $verdict->isCertain() ? 'text-fit' : '' }}" />
                        {{ $verdict->label() }}@if($vehicle) · {{ $vehicle->label() }}@endif
                    </p>
                    <p class="text-sm text-ink2">{{ $verdict->explanation() }}</p>
                    @unless($vehicle)
                        <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'car' })" class="mt-1 w-fit text-sm font-semibold underline underline-offset-2">Alege-ți mașina</button>
                    @endunless
                </div>

                <div class="grid gap-3">
                    <div class="flex items-stretch gap-3">
                        <div class="flex h-[3.25rem] shrink-0 items-center rounded-[3px] border border-line2 bg-white">
                            <button type="button" @click="$refs.qty.stepDown(); $refs.qty.dispatchEvent(new Event('input'))" class="grid h-full w-11 place-items-center transition hover:bg-light" aria-label="Scade cantitatea">
                                <x-storefront.icon name="minus" class="h-4 w-4" />
                            </button>
                            <label>
                                <span class="sr-only">Cantitate</span>
                                <input x-ref="qty" type="number" min="1" max="99" wire:model="quantity"
                                       class="h-full min-h-0 w-12 rounded-none border-0 bg-transparent p-0 text-center font-mono tabular-nums focus:shadow-none focus:ring-0">
                            </label>
                            <button type="button" @click="$refs.qty.stepUp(); $refs.qty.dispatchEvent(new Event('input'))" class="grid h-full w-11 place-items-center transition hover:bg-light" aria-label="Crește cantitatea">
                                <x-storefront.icon name="plus" class="h-4 w-4" />
                            </button>
                        </div>

                        <button wire:click="addToCart" @disabled(! $availability->orderable()) class="st-btn flex-1">
                            <span wire:loading.remove wire:target="addToCart">Adaugă în coș</span>
                            <span wire:loading wire:target="addToCart">Se adaugă…</span>
                            <x-storefront.icon name="cart" wire:loading.remove wire:target="addToCart" />
                        </button>
                    </div>

                    <button wire:click="toggleWishlist" class="st-btn st-btn--outline">
                        <x-storefront.icon name="heart" /> Salvează la favorite
                    </button>

                    @error('quantity') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    @if(session('cart-added'))
                        <p class="flex items-center gap-2 text-sm font-semibold text-fit"><x-storefront.icon name="check" class="h-4 w-4" /> {{ session('cart-added') }}</p>
                    @endif
                    @if(session('wishlist'))
                        <p class="text-sm font-semibold text-ink">{{ session('wishlist') }}</p>
                    @endif
                </div>

                <ul class="grid gap-2.5 border-t border-line pt-5 text-sm text-ink2">
                    <li class="flex items-center gap-3">
                        <x-storefront.icon name="truck" class="h-5 w-5 shrink-0 text-ink" /> Livrare prin curier în toată țara
                    </li>
                    @if($product->warranty_months)
                        <li class="flex items-center gap-3">
                            <x-storefront.icon name="shield" class="h-5 w-5 shrink-0 text-ink" /> Garanție {{ $product->warranty_months }} luni
                        </li>
                    @endif
                    <li class="flex items-center gap-3">
                        <x-storefront.icon name="wrench" class="h-5 w-5 shrink-0 text-ink" />
                        <a href="{{ route('storefront.services') }}" class="underline underline-offset-2 hover:text-ink">Găsește un service care îl montează</a>
                    </li>
                </ul>

                @if($payments->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach($payments as $method)
                            @if(isset($methodLabels[$method]))
                                <li class="rounded-[3px] border border-line2 px-2 py-1 text-[11px] font-semibold text-ink2">{{ $methodLabels[$method] }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if($addOns->isNotEmpty())
        {{-- Its own ground, edge to edge, one row: a single rail cannot be mistaken for more
             results, which a grid here would be. --}}
        <section class="mt-20 bg-g0 py-16 text-bone">
            <div class="shell">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
                    <div class="grid gap-4">
                        <p class="st-kicker text-mute">Se montează cu</p>
                        <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Alte piese pentru aceleași mașini</h2>
                    </div>
                </div>

                <div data-st-carousel data-st-cursor="Trage">
                    <ul class="st-rail">
                        @foreach($addOns as $addOn)
                            <li class="w-[min(74vw,19rem)]">
                                @include('livewire.storefront.partials.product-card', ['product' => $addOn, 'verdict' => null])
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex items-center gap-6">
                        <span class="min-w-16 font-mono text-[13px] text-mute" data-st-counter></span>
                        <div class="st-progress"><i></i></div>
                        <div class="flex gap-2 max-sm:hidden">
                            <button type="button" class="st-round" data-st-prev aria-label="Înapoi"><x-storefront.icon name="arrow-left" class="h-5 w-5" /></button>
                            <button type="button" class="st-round" data-st-next aria-label="Înainte"><x-storefront.icon name="arrow-right" class="h-5 w-5" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="shell mt-20 grid gap-20">
        @if($product->short_description || $product->description || $highlights !== [])
            <section class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="grid content-start gap-5">
                    <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Descriere</h2>
                    @if($product->short_description)
                        <p class="max-w-[62ch] text-[1.2rem] leading-relaxed text-ink">{{ $product->short_description }}</p>
                    @endif
                    @if($product->description)
                        <div class="st-prose">
                            {!! app(\App\Support\HtmlSanitizer::class)->clean($product->description) !!}
                        </div>
                    @endif
                </div>

                @if($highlights !== [])
                    <aside class="h-fit rounded-[3px] bg-sand p-6 text-ink">
                        <h3 class="st-kicker mb-4 text-sandink">Pe scurt</h3>
                        <ul class="grid gap-3 text-[15px]">
                            @foreach($highlights as $highlight)
                                <li class="flex gap-2.5">
                                    <x-storefront.icon name="check" class="mt-1 h-4 w-4 shrink-0 text-signal" />
                                    <span>{{ $highlight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </aside>
                @endif
            </section>
        @endif

        @if($specifications->isNotEmpty())
            <section class="grid gap-6">
                <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Specificații</h2>
                <div class="overflow-x-auto rounded-[3px] border border-line bg-white">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($specifications as $specification)
                                @php($value = $specification->displayValue())
                                @if($value !== null)
                                    <tr class="border-b border-line last:border-b-0">
                                        <th scope="row" class="w-1/2 px-5 py-3.5 text-left font-mono text-[11px] font-normal uppercase tracking-[.08em] text-ink2">{{ $specification->attribute->name }}</th>
                                        <td class="px-5 py-3.5 font-medium">{{ $value }}</td>
                                    </tr>
                                @endif
                            @endforeach
                            @if($product->weight_kg)
                                <tr class="border-b border-line last:border-b-0">
                                    <th scope="row" class="px-5 py-3.5 text-left font-mono text-[11px] font-normal uppercase tracking-[.08em] text-ink2">Greutate</th>
                                    <td class="px-5 py-3.5 font-medium">{{ rtrim(rtrim((string) $product->weight_kg, '0'), '.') }} kg</td>
                                </tr>
                            @endif
                            @if(is_array($product->dimensions_cm) && $product->dimensions_cm !== [])
                                <tr class="border-b border-line last:border-b-0">
                                    <th scope="row" class="px-5 py-3.5 text-left font-mono text-[11px] font-normal uppercase tracking-[.08em] text-ink2">Dimensiuni ambalaj</th>
                                    <td class="px-5 py-3.5 font-medium">
                                        {{ collect($product->dimensions_cm)->filter()->implode(' × ') }} cm
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="grid gap-6">
            <div class="grid gap-3">
                <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Compatibilitate</h2>

                @if($product->is_universal)
                    <p class="text-ink2">Produs universal — nu depinde de modelul mașinii.</p>
                @elseif($product->fitments->isEmpty())
                    <p class="text-ink2">
                        Nu avem încă lista de compatibilitate pentru acest produs.
                        <a href="{{ route('storefront.contact') }}" class="font-semibold text-ink underline underline-offset-2">Întreabă-ne</a> înainte de comandă.
                    </p>
                @else
                    <p class="text-sm text-ink2">Mașinile pentru care producătorul sau furnizorul declară potrivire.</p>
                @endif
            </div>

            @if(! $product->is_universal && $product->fitments->isNotEmpty())
                <ul class="grid gap-px overflow-hidden rounded-[3px] border border-line bg-line text-sm sm:grid-cols-2">
                    @foreach($product->fitments as $fitment)
                        <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 bg-white px-5 py-3.5">
                            <span class="font-semibold">
                                {{ $fitment->make?->name ?? 'Orice marcă' }} {{ $fitment->model?->name }}
                            </span>
                            @if($fitment->generation?->name)<span class="text-ink2">{{ $fitment->generation->name }}</span>@endif
                            @if($fitment->year_from || $fitment->year_to)
                                <span class="font-mono text-xs text-ink2">{{ $fitment->year_from ?? '…' }}–{{ $fitment->year_to ?? '…' }}</span>
                            @endif
                            @if($fitment->requires_modification)
                                <span class="pill-warning">necesită modificări</span>
                            @endif
                            @if($fitment->notes)<span class="w-full text-xs text-ink2">{{ $fitment->notes }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($product->collections->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($product->collections as $collection)
                        <a href="{{ $collection->url() }}" class="st-chip bg-white hover:border-ink">
                            <x-storefront.icon name="car" class="h-4 w-4" /> Tot pentru {{ $collection->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @if($downloads->isNotEmpty())
            <section class="grid gap-5">
                <h2 class="st-display text-[clamp(1.6rem,2.6vw,2.4rem)]">Documente</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach($downloads as $download)
                        <li>
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk($download->disk)->url($download->path) }}"
                               target="_blank" rel="noopener"
                               class="flex items-center justify-between gap-3 rounded-[3px] border border-line bg-white px-4 py-3.5 font-medium transition hover:border-ink">
                                {{ $download->alt_text ?: 'Document' }}
                                <x-storefront.icon name="download" class="h-4 w-4 shrink-0 text-ink2" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($reviews->isNotEmpty())
            <section class="grid gap-6">
                <div class="grid gap-3">
                    <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Ce spun clienții</h2>
                    {{-- Said plainly because the rules on review transparency require it: these are
                         not anonymous submissions, and the page has to say where they came from. --}}
                    <p class="text-sm text-ink2">
                        Recenzii primite de la clienți care au cumpărat produsul și publicate de noi.
                    </p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($reviews as $review)
                        <x-storefront.review-card :review="$review" />
                    @endforeach
                </div>
            </section>
        @endif

        @if($related->isNotEmpty())
            <section class="grid gap-6">
                <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Ai putea avea nevoie și de</h2>
                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    @foreach($related as $relatedProduct)
                        @include('livewire.storefront.partials.product-card', ['product' => $relatedProduct, 'verdict' => null])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
