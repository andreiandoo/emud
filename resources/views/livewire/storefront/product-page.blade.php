@php($settings = app(\App\Settings\StoreSettings::class))
@php($methodLabels = \App\Livewire\Admin\Settings\FooterSettings::PAYMENT_METHODS)
@php($payments = collect($settings->array('footer_payment_methods')))
@php($currency = $variant?->currency ?? config('emud.catalog.default_currency', 'RON'))
@php($price = $variant?->retail_price !== null ? \App\Support\Money::of($variant->retail_price, $currency)->format() : null)
@php($compareAt = $price !== null && $variant?->compare_at_price !== null && (float) $variant->compare_at_price > (float) $variant->retail_price
        ? \App\Support\Money::of($variant->compare_at_price, $currency)->format() : null)
@php($discount = $compareAt ? (int) round((1 - (float) $variant->retail_price / (float) $variant->compare_at_price) * 100) : null)
@php($urls = $gallery->map(fn ($medium) => \Illuminate\Support\Facades\Storage::disk($medium->disk)->url($medium->path))->values())
@php($rated = $reviews->filter(fn ($review) => $review->rating !== null))
@php($average = $rated->isEmpty() ? null : round((float) $rated->avg('rating'), 1))
@php($stars = $average === null ? 0 : (int) round($average))
@php($fitmentCount = $product->fitments->count())
@php($hasDimensions = is_array($product->dimensions_cm) && array_filter($product->dimensions_cm) !== [])
@php($hasSpecs = $specifications->isNotEmpty() || $product->weight_kg || $hasDimensions)
@php($incompatible = $verdict === \App\Storefront\Compatibility\CompatibilityVerdict::Incompatible)
@php($unknown = $verdict === \App\Storefront\Compatibility\CompatibilityVerdict::Unknown)
{{-- Only these two are drawn with a tick. fits() is also true for "unknown" and "needs more
     detail", and a tick beside either would read as a promise the data cannot make. --}}
@php($fitsForSure = in_array($verdict, [\App\Storefront\Compatibility\CompatibilityVerdict::Confirmed, \App\Storefront\Compatibility\CompatibilityVerdict::Conditional], true))

{{-- `bar` is the buy bar at the bottom of the window: it slides in once the buy box has scrolled
     away above, so the button is never more than a thumb away on a long page. --}}
<div x-data="{ bar: false }">
    <x-seo :title="$product->name"
           :description="$product->short_description"
           :canonical="route('storefront.product', $product)"
           :image="$urls->first()"
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

    {{-- ============================================================ The stage --}}
    {{-- Photograph on the left, held in place while the right-hand column scrolls: whatever the
         customer is reading — a spec row, the list of cars — the part stays in view. --}}
    <section class="bg-light">
        <div class="shell pt-6 sm:pt-8">
            <nav class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 font-mono text-[11px] uppercase tracking-[.1em] text-ink2" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-ink">Acasă</a>
                @foreach($trail as $crumb)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('storefront.category', $crumb) }}" class="transition hover:text-ink">{{ $crumb->name }}</a>
                @endforeach
                <span aria-hidden="true">/</span>
                <span class="max-w-[42ch] truncate text-ink">{{ $product->name }}</span>
            </nav>
        </div>

        <div class="shell grid gap-10 pb-16 pt-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-14 lg:pb-24 xl:gap-20">
            {{-- ---------- Gallery. Entirely in Alpine: changing photographs is a local decision and
                 needs no round trip to be right. --}}
            <div class="lg:sticky lg:top-[calc(var(--st-header-visible,0px)+1.25rem)] lg:self-start lg:transition-[top] lg:duration-500"
                 x-data="{
                     active: 0,
                     count: {{ max(1, $gallery->count()) }},
                     urls: @js($urls),
                     zoom: false,
                     box: false,
                     sx: 0,
                     go(index) { this.active = (index + this.count) % this.count },
                     aim(event) {
                         const r = event.currentTarget.getBoundingClientRect();
                         event.currentTarget.style.setProperty('--zx', ((event.clientX - r.left) / r.width * 100) + '%');
                         event.currentTarget.style.setProperty('--zy', ((event.clientY - r.top) / r.height * 100) + '%');
                     },
                     swipe(event) {
                         const dx = event.changedTouches[0].clientX - this.sx;
                         if (Math.abs(dx) > 40) this.go(this.active + (dx < 0 ? 1 : -1));
                     },
                 }">
                <div @class(['grid gap-3', 'lg:grid-cols-[4.75rem_minmax(0,1fr)]' => $gallery->count() > 1])>
                    @if($gallery->count() > 1)
                        <ul class="order-2 flex gap-2 overflow-x-auto [scrollbar-width:none] lg:order-1 lg:max-h-[38rem] lg:flex-col lg:overflow-y-auto" data-lenis-prevent>
                            @foreach($gallery as $index => $medium)
                                <li class="shrink-0">
                                    <button type="button" @click="go({{ $index }})"
                                            :aria-current="active === {{ $index }} ? 'true' : 'false'"
                                            :class="active === {{ $index }} ? 'border-ink' : 'border-line hover:border-line2'"
                                            class="block h-[4.75rem] w-[4.75rem] overflow-hidden rounded-[3px] border-2 bg-white transition"
                                            aria-label="Imaginea {{ $index + 1 }}">
                                        <img src="{{ $urls[$index] }}" alt="" loading="lazy" class="h-full w-full object-contain p-1.5 mix-blend-multiply">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- The photograph enlarges under a mouse pointer and opens full screen on a click
                         or a tap; on a phone it also follows a swipe. --}}
                    <div class="st-zoom relative order-1 aspect-square overflow-hidden rounded-[3px] border border-line bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)] lg:order-2 {{ $gallery->isNotEmpty() ? 'cursor-zoom-in' : '' }}"
                         :class="zoom && 'is-zoomed'"
                         @if($gallery->isNotEmpty())
                             @mouseenter="zoom = window.matchMedia('(hover: hover) and (pointer: fine)').matches"
                             @mouseleave="zoom = false"
                             @mousemove="aim($event)"
                             @click="zoom = false; box = true"
                             @touchstart.passive="sx = $event.touches[0].clientX"
                             @touchend="swipe($event)"
                         @endif>
                        @forelse($gallery as $index => $medium)
                            {{-- x-cloak on all but the first: x-show only applies once Alpine has
                                 booted, and a slow load would otherwise flash every photograph. --}}
                            <img x-show="active === {{ $index }}" @if($index > 0) x-cloak @endif
                                 x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0"
                                 src="{{ $urls[$index] }}"
                                 alt="{{ $medium->alt_text ?? $product->name }}"
                                 @if($index > 0) loading="lazy" @else fetchpriority="high" @endif
                                 class="absolute inset-0 h-full w-full object-contain p-8 mix-blend-multiply sm:p-14">
                        @empty
                            <span class="absolute inset-0 grid place-content-center justify-items-center gap-3 text-sm text-ink2">
                                <x-storefront.icon name="part" class="h-16 w-16 text-line2" />
                                Fără imagine
                            </span>
                        @endforelse

                        <div class="pointer-events-none absolute left-4 top-4 flex flex-wrap gap-1.5">
                            @if($discount)
                                <span class="rounded-[2px] bg-signal px-2 py-1 font-mono text-[11px] font-semibold text-ink">−{{ $discount }}%</span>
                            @endif
                            @if($vehicle && $verdict->isCertain())
                                <span class="inline-flex items-center gap-1.5 rounded-[2px] bg-fit px-2 py-1 text-[10px] font-semibold uppercase tracking-[.1em] text-white">
                                    <x-storefront.icon name="check" class="h-3 w-3" /> Se potrivește
                                </span>
                            @endif
                            @if($product->is_universal)
                                <span class="rounded-[2px] bg-ink px-2 py-1 text-[10px] font-semibold uppercase tracking-[.1em] text-light">Universal</span>
                            @endif
                        </div>

                        @if($gallery->count() > 1)
                            <button type="button" @click.stop="go(active - 1)"
                                    class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-line2 bg-white/90 transition hover:border-ink max-sm:hidden"
                                    aria-label="Imaginea anterioară">
                                <x-storefront.icon name="arrow-left" class="h-4 w-4" />
                            </button>
                            <button type="button" @click.stop="go(active + 1)"
                                    class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-line2 bg-white/90 transition hover:border-ink max-sm:hidden"
                                    aria-label="Imaginea următoare">
                                <x-storefront.icon name="arrow-right" class="h-4 w-4" />
                            </button>
                        @endif

                        @if($gallery->isNotEmpty())
                            <span class="pointer-events-none absolute bottom-4 right-4 inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 font-mono text-[11px] text-ink2">
                                <x-storefront.icon name="expand" class="h-3.5 w-3.5" />
                                @if($gallery->count() > 1)
                                    <span x-text="(active + 1) + ' / ' + count">1 / {{ $gallery->count() }}</span>
                                @else
                                    Mărește
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Full screen, moved to the end of the page so no parent's overflow or stacking
                     can crop it. The photographs sit on their own pale ground: supplier shots come
                     on white, and white on graphite would be a hole in the page. --}}
                @if($gallery->isNotEmpty())
                    <template x-teleport="body">
                        <div x-show="box" x-cloak x-transition.opacity.duration.300ms x-trap.noscroll="box"
                             @keydown.escape.window="box = false"
                             @keydown.arrow-right.window="if (box) go(active + 1)"
                             @keydown.arrow-left.window="if (box) go(active - 1)"
                             class="fixed inset-0 z-[80] flex flex-col bg-g0/95 text-bone backdrop-blur-sm"
                             role="dialog" aria-modal="true" aria-label="Fotografiile produsului">
                            <div class="flex items-center justify-between gap-4 px-[var(--st-pad)] py-4">
                                <p class="min-w-0 truncate font-display text-lg font-semibold">{{ $product->name }}</p>
                                <div class="flex shrink-0 items-center gap-4">
                                    @if($gallery->count() > 1)
                                        <span class="font-mono text-sm text-mute" x-text="(active + 1) + ' / ' + count"></span>
                                    @endif
                                    <button type="button" @click="box = false" class="st-round text-bone" aria-label="Închide">
                                        <x-storefront.icon name="close" class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <div class="relative flex min-h-0 flex-1 items-center justify-center px-[var(--st-pad)] pb-6"
                                 @touchstart.passive="sx = $event.touches[0].clientX" @touchend="swipe($event)">
                                <div class="relative h-full w-full max-w-5xl overflow-hidden rounded-[3px] bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)]">
                                    <img :src="urls[active]" alt="{{ $product->name }}" class="absolute inset-0 h-full w-full object-contain p-6 mix-blend-multiply sm:p-12">
                                </div>

                                @if($gallery->count() > 1)
                                    <button type="button" @click="go(active - 1)" class="st-round absolute left-[calc(var(--st-pad)+.75rem)] top-1/2 -translate-y-1/2 bg-g0/70 text-bone max-sm:hidden" aria-label="Imaginea anterioară">
                                        <x-storefront.icon name="arrow-left" class="h-5 w-5" />
                                    </button>
                                    <button type="button" @click="go(active + 1)" class="st-round absolute right-[calc(var(--st-pad)+.75rem)] top-1/2 -translate-y-1/2 bg-g0/70 text-bone max-sm:hidden" aria-label="Imaginea următoare">
                                        <x-storefront.icon name="arrow-right" class="h-5 w-5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    </template>
                @endif
            </div>

            {{-- ---------- Everything about buying it, then everything about the part. --}}
            <div class="grid content-start gap-8">
                <header class="grid gap-4">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 font-mono text-[11px] uppercase tracking-[.12em] text-ink2">
                        @if($product->brand)
                            <a href="{{ route('storefront.search', ['q' => $product->brand->name]) }}" class="font-medium text-ink transition hover:text-signal">{{ $product->brand->name }}</a>
                        @endif
                        @if($product->sku)<span>Cod {{ $product->sku }}</span>@endif
                        @if($product->manufacturer_part_number)<span>MPN {{ $product->manufacturer_part_number }}</span>@endif
                    </div>

                    <h1 class="st-display text-[clamp(2.1rem,3.6vw,3.6rem)] leading-[1]">{{ $product->name }}</h1>

                    @if($average !== null)
                        <a href="#recenzii" class="flex w-fit items-center gap-2.5 text-sm text-ink2 transition hover:text-ink">
                            <span class="tracking-[.12em] text-signal" aria-hidden="true">{{ str_repeat('★', $stars) }}<span class="text-line2">{{ str_repeat('★', 5 - $stars) }}</span></span>
                            <span><span class="font-semibold text-ink">{{ number_format($average, 1, ',', '') }}</span> · {{ $rated->count() }} {{ $rated->count() === 1 ? 'recenzie' : 'recenzii' }}</span>
                        </a>
                    @endif

                    @if($product->short_description)
                        <p class="max-w-[58ch] text-[1.075rem] leading-relaxed text-ink2">{{ $product->short_description }}</p>
                    @endif
                </header>

                <div class="grid gap-2.5 border-t border-line pt-6">
                    <div class="flex flex-wrap items-end gap-x-4 gap-y-2">
                        <span class="font-display text-[clamp(2.4rem,3.6vw,3.25rem)] font-semibold leading-none tracking-[-.02em] tabular-nums">{{ $price ?? 'Preț la cerere' }}</span>
                        @if($compareAt)
                            <span class="pb-1 text-lg text-ink2 line-through">{{ $compareAt }}</span>
                        @endif
                    </div>

                    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
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
                        @if($availability->deliveryWindow())
                            <span class="text-ink2">{{ $availability->deliveryWindow() }}</span>
                        @elseif($availability->dispatchWindow())
                            <span class="text-ink2">{{ $availability->dispatchWindow() }}</span>
                        @endif
                        @if($price)
                            <span class="text-ink2">· TVA inclus</span>
                        @endif
                    </p>

                    {{-- Stock by country when the part can be had from more than one place, or from one
                         we can name: a warehouse here and one in Poland are different waits. --}}
                    @php($stocked = $availability->stockedSources())
                    @if(count($stocked) > 1 || (count($stocked) === 1 && $stocked[0]['country'] !== null))
                        <div class="mt-1 overflow-hidden rounded-[3px] border border-line bg-white">
                            <p class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-line bg-light px-3.5 py-2 font-mono text-[10.5px] uppercase tracking-[.1em] text-ink2">
                                <span>{{ count($stocked) > 1 ? 'Stoc disponibil la '.count($stocked).' furnizori' : 'Stoc la furnizor' }}</span>
                                @unless($availability->confirmed)
                                    <span class="normal-case tracking-normal">confirmăm stocul la comandă</span>
                                @endunless
                            </p>
                            <ul class="divide-y divide-line text-[13px]">
                                @foreach($stocked as $source)
                                    <li class="flex flex-wrap items-center gap-x-2.5 gap-y-1 px-3.5 py-2.5">
                                        @if($source['country'])
                                            <span class="rounded-[2px] bg-ink px-1.5 py-0.5 font-mono text-[10.5px] font-semibold tracking-[.06em] text-light">{{ $source['country'] }}</span>
                                        @endif
                                        <span @class([
                                            'font-semibold',
                                            'text-fit' => $source['status'] === \App\Enums\StockStatus::InStock,
                                            'text-amber-700' => $source['status'] !== \App\Enums\StockStatus::InStock,
                                        ])>{{ \App\Storefront\Availability::sourceLabel($source['status']) }}</span>
                                        @if($source['delivery'])
                                            <span class="text-ink2">· livrabil în {{ \App\Storefront\Availability::range($source['delivery']) }} zile lucrătoare</span>
                                        @endif
                                        @if($shippingPrice)
                                            <span class="ml-auto text-xs text-ink2">livrare {{ $shippingPrice->isZero() ? 'gratuită' : $shippingPrice->format() }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Fit, in one line above the button: it decides whether this is the right part, so it
                     is read before the order — without taking the buy box's room to say it. --}}
                @php($connector = $incompatible ? 'cu' : ($fitsForSure ? 'pe' : 'pentru'))
                <div title="{{ $verdict->explanation() }}" @class([
                    'flex flex-wrap items-center gap-x-4 gap-y-2 rounded-[3px] border px-3.5 py-2.5 text-[13.5px]',
                    'border-fit/30 bg-fit/[.06]' => $fitsForSure,
                    'border-signal/35 bg-signal/[.06]' => $incompatible,
                    'border-amber-300 bg-amber-50' => ! $fitsForSure && ! $incompatible && ! $unknown,
                    'border-line bg-white' => $unknown,
                ])>
                    <p class="flex min-w-0 flex-1 items-center gap-2.5">
                        <span @class([
                            'grid h-6 w-6 shrink-0 place-items-center rounded-full',
                            'bg-fit text-white' => $fitsForSure,
                            'bg-signal text-white' => $incompatible,
                            'bg-amber-500 text-white' => ! $fitsForSure && ! $incompatible && ! $unknown,
                            'bg-line text-ink' => $unknown,
                        ])>
                            <x-storefront.icon :name="$fitsForSure ? 'check' : ($incompatible ? 'close' : 'car')" class="h-3.5 w-3.5" />
                        </span>
                        <span class="min-w-0 leading-snug">
                            @if($vehicle)
                                <span class="font-semibold">{{ $verdict->label() }}</span>
                                <span class="text-ink2">{{ $connector }} {{ $incompatible && $carCount > 1 ? 'mașinile tale din garaj' : $vehicle->label() }}</span>
                            @else
                                <span class="font-semibold">Se potrivește pe mașina ta?</span>
                                <span class="text-ink2">Alege mașina și îți spunem.</span>
                            @endif
                        </span>
                    </p>

                    <span class="flex shrink-0 items-center gap-3 text-[12.5px] font-semibold">
                        <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'car' })" class="underline underline-offset-2 transition hover:text-signal">
                            {{ $vehicle ? 'Schimbă' : 'Alege mașina' }}
                        </button>
                        @unless($vehicle)
                            <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'vin' })" class="underline underline-offset-2 transition hover:text-signal">După VIN</button>
                        @endunless
                        @if(! $product->is_universal && $fitmentCount > 0)
                            <a href="#compatibilitate" @click="$dispatch('open-section', 'compatibilitate')" class="underline underline-offset-2 transition hover:text-signal">Toate mașinile ({{ $fitmentCount }})</a>
                        @endif
                    </span>
                </div>

                <div class="grid gap-3" x-intersect:leave="bar = $el.getBoundingClientRect().top < 0" x-intersect:enter="bar = false">
                    <div class="flex items-stretch gap-2.5">
                        <div class="flex h-[3.5rem] shrink-0 items-center rounded-[3px] border border-line2 bg-white">
                            <button type="button" @click="$refs.qty.stepDown(); $refs.qty.dispatchEvent(new Event('input'))" class="grid h-full w-11 place-items-center transition hover:bg-light" aria-label="Scade cantitatea">
                                <x-storefront.icon name="minus" class="h-4 w-4" />
                            </button>
                            <label>
                                <span class="sr-only">Cantitate</span>
                                <input x-ref="qty" type="number" min="1" max="99" wire:model="quantity"
                                       class="h-full min-h-0 w-11 rounded-none border-0 bg-transparent p-0 text-center font-mono tabular-nums focus:shadow-none focus:ring-0">
                            </label>
                            <button type="button" @click="$refs.qty.stepUp(); $refs.qty.dispatchEvent(new Event('input'))" class="grid h-full w-11 place-items-center transition hover:bg-light" aria-label="Crește cantitatea">
                                <x-storefront.icon name="plus" class="h-4 w-4" />
                            </button>
                        </div>

                        <button wire:click="addToCart" @disabled(! $availability->orderable()) class="st-btn min-h-[3.5rem] flex-1">
                            <span wire:loading.remove wire:target="addToCart">Adaugă în coș</span>
                            <span wire:loading wire:target="addToCart">Se adaugă…</span>
                            <x-storefront.icon name="cart" wire:loading.remove wire:target="addToCart" />
                        </button>

                        {{-- Filled once saved, so the button says where the part already is. --}}
                        <button wire:click="toggleWishlist" aria-pressed="{{ $inWishlist ? 'true' : 'false' }}"
                                aria-label="{{ $inWishlist ? 'Scoate din favorite' : 'Salvează la favorite' }}"
                                title="{{ $inWishlist ? 'Salvat la favorite' : 'Salvează la favorite' }}" @class([
                                    'group grid h-[3.5rem] w-[3.5rem] shrink-0 place-items-center rounded-[3px] border transition duration-300',
                                    'border-signal bg-signal/10 text-signal' => $inWishlist,
                                    'border-line2 bg-white text-ink hover:border-signal hover:bg-signal/[.06] hover:text-signal' => ! $inWishlist,
                                ])>
                            <x-storefront.icon name="heart" :class="'h-5 w-5 transition-transform duration-300 group-hover:scale-110 group-active:scale-90'.($inWishlist ? ' fill-current' : '')" />
                        </button>
                    </div>

                    @error('quantity') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    @if(session('cart-added'))
                        <p class="flex items-center gap-2 text-sm font-semibold text-fit"><x-storefront.icon name="check" class="h-4 w-4" /> {{ session('cart-added') }}</p>
                    @endif
                    @if(session('wishlist'))
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink"><x-storefront.icon name="heart" class="h-4 w-4" /> {{ session('wishlist') }}</p>
                    @endif
                </div>

                <ul class="grid gap-2 sm:grid-cols-3">
                    @foreach([
                        [
                            'truck',
                            $shippingPrice === null ? 'Livrare prin curier' : ($shippingPrice->isZero() ? 'Livrare gratuită' : 'Livrare '.$shippingPrice->format()),
                            $availability->deliveryWindow() ?? ($freeOver !== null && $shippingPrice !== null && ! $shippingPrice->isZero() ? 'Gratuită de la '.$freeOver->format() : 'Prin curier, în toată țara'),
                        ],
                        ['return', 'Retur în 14 zile', 'De la primire, conform legii'],
                        ['shield', 'Garanție '.($product->warranty_months ? $product->warranty_months.' luni' : 'legală'), 'Factura ține loc de certificat'],
                    ] as [$icon, $title, $detail])
                        <li class="group flex items-center gap-3 rounded-[3px] border border-line bg-white p-3 transition-colors duration-300 hover:border-line2">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sand text-sandink transition-colors duration-300 group-hover:bg-ink group-hover:text-light">
                                <x-storefront.icon :name="$icon" class="h-[1.15rem] w-[1.15rem]" />
                            </span>
                            <span class="min-w-0 leading-snug">
                                <span class="block text-[13px] font-semibold text-ink">{{ $title }}</span>
                                <span class="block text-xs text-ink2">{{ $detail }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- The workshops near the customer that do the job this part needs, in a dialog over
                     the page rather than instead of it: the customer came here to buy the part. --}}
                <button type="button" @click="$dispatch('open-mount-shops'); $wire.findShops()"
                        class="group flex w-full items-center gap-4 rounded-[3px] bg-g1 px-4 py-3.5 text-left text-bone transition-colors duration-300 hover:bg-g0">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-signal text-ink transition-transform duration-500 group-hover:-rotate-12">
                        <x-storefront.icon name="wrench" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">Găsește un service care îl montează</span>
                        <span class="block truncate text-xs text-mute">
                            {{ collect([$mountService?->name, $mountLocation['city'] ? 'lângă '.$mountLocation['city'] : ($mountLocation['county'] ? 'în județul '.$mountLocation['county'] : 'ateliere din toată țara')])->filter()->implode(' · ') }}
                        </span>
                    </span>
                    <x-storefront.icon name="arrow-right" class="h-4 w-4 shrink-0 transition-transform duration-300 group-hover:translate-x-1" />
                </button>

                @if($payments->isNotEmpty())
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach($payments as $method)
                            @if(isset($methodLabels[$method]))
                                <li class="rounded-[3px] border border-line2 px-2 py-1 text-[11px] font-semibold text-ink2">{{ $methodLabels[$method] }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                {{-- ---------- The part itself, one section at a time. --}}
                <div class="border-b border-line">
                    @if($product->description)
                        <x-storefront.disclosure title="Descriere" :open="true" name="descriere">
                            <div class="st-prose text-base">
                                {!! app(\App\Support\HtmlSanitizer::class)->clean($product->description) !!}
                            </div>
                        </x-storefront.disclosure>
                    @endif

                    @if($hasSpecs)
                        <x-storefront.disclosure title="Specificații" :open="! $product->description" name="specificatii">
                            <dl class="grid text-sm">
                                @foreach($specifications as $specification)
                                    @php($value = $specification->displayValue())
                                    @if($value !== null)
                                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] gap-4 border-b border-line py-3 last:border-b-0">
                                            <dt class="font-mono text-[11px] uppercase leading-5 tracking-[.08em] text-ink2">{{ $specification->attribute->name }}</dt>
                                            <dd class="font-medium">{{ $value }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                                @if($product->weight_kg)
                                    <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] gap-4 border-b border-line py-3 last:border-b-0">
                                        <dt class="font-mono text-[11px] uppercase leading-5 tracking-[.08em] text-ink2">Greutate</dt>
                                        <dd class="font-medium">{{ rtrim(rtrim((string) $product->weight_kg, '0'), '.') }} kg</dd>
                                    </div>
                                @endif
                                @if($hasDimensions)
                                    <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] gap-4 border-b border-line py-3 last:border-b-0">
                                        <dt class="font-mono text-[11px] uppercase leading-5 tracking-[.08em] text-ink2">Dimensiuni ambalaj</dt>
                                        <dd class="font-medium">{{ collect($product->dimensions_cm)->filter()->implode(' × ') }} cm</dd>
                                    </div>
                                @endif
                            </dl>
                        </x-storefront.disclosure>
                    @endif

                    <x-storefront.disclosure title="Compatibilitate" name="compatibilitate"
                        :meta="$product->is_universal ? 'Universal' : ($fitmentCount > 0 ? $fitmentCount.' '.($fitmentCount === 1 ? 'mașină' : 'mașini') : null)">
                        <div class="grid gap-5">
                            @if($product->is_universal)
                                <p class="text-ink2">Produs universal: nu depinde de modelul mașinii.</p>
                            @elseif($fitmentCount === 0)
                                <p class="text-ink2">
                                    Nu avem încă lista de compatibilitate pentru acest produs.
                                    <a href="{{ route('storefront.contact') }}" class="font-semibold text-ink underline underline-offset-2">Întreabă-ne</a> înainte de comandă.
                                </p>
                            @else
                                <p class="text-sm text-ink2">Mașinile pentru care producătorul sau furnizorul declară potrivire.</p>
                                <ul class="grid gap-px overflow-hidden rounded-[3px] border border-line bg-line text-sm">
                                    @foreach($product->fitments as $fitment)
                                        <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1 bg-white px-4 py-3">
                                            <span class="font-semibold">{{ $fitment->make?->name ?? 'Orice marcă' }} {{ $fitment->model?->name }}</span>
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
                        </div>
                    </x-storefront.disclosure>

                    <x-storefront.disclosure title="Livrare, retur și garanție" name="livrare">
                        <ul class="grid gap-4 text-[15px] text-ink2">
                            <li class="flex gap-3">
                                <x-storefront.icon name="truck" class="mt-0.5 h-5 w-5 shrink-0 text-ink" />
                                <span>
                                    <strong class="text-ink">Livrare prin curier în toată țara{{ $shippingPrice ? ($shippingPrice->isZero() ? ', gratuit' : ', '.$shippingPrice->format()) : '' }}.</strong>
                                    {{ $availability->deliveryWindow() ?? $availability->dispatchWindow() }}
                                    @if($freeOver && $shippingPrice && ! $shippingPrice->isZero()) Gratuită pentru comenzi de la {{ $freeOver->format() }}. @endif
                                </span>
                            </li>
                            <li class="flex gap-3">
                                <x-storefront.icon name="return" class="mt-0.5 h-5 w-5 shrink-0 text-ink" />
                                <span><strong class="text-ink">14 zile pentru retur.</strong> Ai 14 zile de la primire să renunți la comandă, conform legii.</span>
                            </li>
                            <li class="flex gap-3">
                                <x-storefront.icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-ink" />
                                <span><strong class="text-ink">Garanție {{ $product->warranty_months ? $product->warranty_months.' luni' : 'legală de conformitate' }}.</strong> Păstrează factura: e documentul de garanție.</span>
                            </li>
                            <li class="flex gap-3">
                                <x-storefront.icon name="wrench" class="mt-0.5 h-5 w-5 shrink-0 text-ink" />
                                <span>
                                    <strong class="text-ink">Montaj.</strong>
                                    <button type="button" @click="$dispatch('open-mount-shops'); $wire.findShops()" class="underline underline-offset-2 hover:text-ink">Găsește un service</button>
                                    care montează piesa, lângă tine.
                                </span>
                            </li>
                        </ul>
                    </x-storefront.disclosure>

                    @if($downloads->isNotEmpty())
                        <x-storefront.disclosure title="Documente" name="documente"
                            :meta="$downloads->count().' '.($downloads->count() === 1 ? 'fișier' : 'fișiere')">
                            <ul class="grid gap-2">
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
                        </x-storefront.disclosure>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================ In short --}}
    @if($highlights !== [])
        <section class="bg-sand text-ink">
            <div class="shell py-14 sm:py-20">
                <p class="st-kicker mb-10 text-sandink">Pe scurt</p>
                <ol class="grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($highlights as $index => $highlight)
                        <li class="grid content-start gap-3 border-t border-sandink/25 pt-5">
                            <span class="font-mono text-sm text-sandink">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="font-display text-[1.35rem] font-semibold leading-snug">{{ $highlight }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- ============================================================ Goes with it --}}
    @if($addOns->isNotEmpty())
        {{-- Its own ground, edge to edge, one row: a single rail cannot be mistaken for more
             results, which a grid here would be. --}}
        <section class="bg-g0 py-16 text-bone sm:py-20">
            <div class="shell">
                <div class="mb-10 grid gap-4">
                    <p class="st-kicker text-mute">Pentru aceeași mașină</p>
                    <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Completează montajul</h2>
                    <p class="max-w-[52ch] text-mute">Alte piese pentru {{ $product->collections->pluck('name')->take(2)->implode(' și ') }}, pentru alte lucrări decât aceasta.</p>
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

    {{-- ============================================================ Fitting --}}
    {{-- Between the car's other parts and the shelf's alternatives, so the two rails read as
         different things — and because a part bought here is a part someone has to fit. --}}
    <section class="border-y border-sandink/15 bg-sand text-ink">
        <div class="shell flex flex-col gap-6 py-12 sm:flex-row sm:items-center sm:justify-between">
            <div class="grid max-w-[48ch] gap-2">
                <p class="st-kicker text-sandink">Montaj</p>
                <h2 class="font-display text-[clamp(1.6rem,2.6vw,2.2rem)] font-semibold leading-tight">Îl montezi la un service de lângă tine</h2>
                <p class="text-sandink">
                    {{ $mountService ? 'Ateliere care fac „'.$mountService->name.'”' : 'Ateliere' }}{{ $mountLocation['city'] ? ' în '.$mountLocation['city'] : ($mountLocation['county'] ? ' din județul '.$mountLocation['county'] : ' din toată țara') }}, cu adresă, program și telefon.
                </p>
            </div>
            <button type="button" @click="$dispatch('open-mount-shops'); $wire.findShops()" class="st-btn st-btn--ink shrink-0">
                <x-storefront.icon name="wrench" /> Vezi atelierele
            </button>
        </div>
    </section>

    {{-- ============================================================ Reviews --}}
    @if($reviews->isNotEmpty())
        <section id="recenzii" class="scroll-mt-24 bg-light">
            <div class="shell grid gap-10 py-20 lg:grid-cols-[18rem_minmax(0,1fr)] lg:gap-16">
                <div class="grid content-start gap-5 lg:sticky lg:top-[calc(var(--st-header-visible,0px)+1.5rem)] lg:self-start">
                    <p class="st-kicker text-ink2">Recenzii</p>
                    <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Ce spun clienții</h2>
                    @if($average !== null)
                        <p class="flex items-end gap-3">
                            <span class="font-display text-6xl font-semibold leading-none tracking-[-.03em] tabular-nums">{{ number_format($average, 1, ',', '') }}</span>
                            <span class="pb-1 text-sm text-ink2">din 5 · {{ $rated->count() }} {{ $rated->count() === 1 ? 'recenzie' : 'recenzii' }}</span>
                        </p>
                    @endif
                    {{-- Said plainly because the rules on review transparency require it: these are
                         not anonymous submissions, and the page has to say where they came from. --}}
                    <p class="text-sm text-ink2">Recenzii primite de la clienți care au cumpărat produsul și publicate de noi.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($reviews as $review)
                        <x-storefront.review-card :review="$review" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============================================================ Same shelf --}}
    @if($related->isNotEmpty())
        <section class="border-t border-line bg-light">
            <div class="shell py-20">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-6">
                    <div class="grid gap-4">
                        <p class="st-kicker text-ink2">Alternative</p>
                        <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Alte variante din {{ $trail->last()?->name ?? 'aceeași categorie' }}</h2>
                        <p class="max-w-[52ch] text-ink2">Aceeași lucrare, alt producător sau altă specificație. Compară înainte să alegi.</p>
                    </div>
                    @if($trail->isNotEmpty())
                        <a href="{{ route('storefront.category', $trail->last()) }}" class="st-link">Toată categoria <x-storefront.icon name="arrow-right" /></a>
                    @endif
                </div>

                <div data-st-carousel data-st-cursor="Trage">
                    <ul class="st-rail">
                        @foreach($related as $relatedProduct)
                            <li class="w-[min(74vw,19rem)]">
                                @include('livewire.storefront.partials.product-card', ['product' => $relatedProduct, 'verdict' => null])
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex items-center gap-6">
                        <span class="min-w-16 font-mono text-[13px] text-ink2" data-st-counter></span>
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

    {{-- ============================================================ Workshops that fit it --}}
    {{-- Opened from the buy box and from the fitting band. A few workshops over the page; the full
         directory opens in a new tab with the same filters, so this page stays where it was. --}}
    <div x-data="{ open: false }" @open-mount-shops.window="open = true" @keydown.escape.window="open = false"
         x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6"
         role="dialog" aria-modal="true" aria-labelledby="mount-shops-title">
        <div @click="open = false" class="absolute inset-0 bg-g0/70 backdrop-blur-sm"></div>

        <div x-trap.noscroll="open" class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-t-[3px] bg-light text-ink shadow-2xl sm:rounded-[3px]">
            <div class="flex items-start justify-between gap-4 border-b border-line p-6">
                <div class="grid gap-1.5">
                    <p class="st-kicker text-ink2">Montaj</p>
                    <h2 id="mount-shops-title" class="font-display text-2xl font-semibold">Ateliere care îl montează</h2>
                    <p class="text-sm text-ink2">
                        {{ collect([$mountService?->name, $mountLocation['city'] ? 'lângă '.$mountLocation['city'] : ($mountLocation['county'] ? 'în județul '.$mountLocation['county'] : null)])->filter()->implode(' · ') ?: 'Ateliere din toată țara' }}
                    </p>
                </div>
                <button type="button" @click="open = false" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4" data-lenis-prevent>
                @if($mountShops === null)
                    <p class="p-6 text-center text-sm text-ink2">Căutăm atelierele…</p>
                @elseif($mountShops['shops']->isEmpty())
                    <p class="p-6 text-center text-sm text-ink2">Nu avem încă ateliere listate pentru asta. Caută în director după oraș.</p>
                @else
                    @if($mountService && ! $mountShops['byService'])
                        <p class="mb-3 rounded-[3px] bg-sand px-3.5 py-2.5 text-xs text-sandink">
                            Niciun atelier din zonă nu listează încă „{{ $mountService->name }}”. Acestea sunt cele mai apropiate: sună-le și întreabă.
                        </p>
                    @endif
                    <ul class="grid gap-2">
                        @foreach($mountShops['shops'] as $shop)
                            <li wire:key="mount-shop-{{ $shop->id }}">
                                <a href="{{ $shop->url() }}" target="_blank" rel="noopener"
                                   class="group flex items-start justify-between gap-3 rounded-[3px] border border-line bg-white p-3.5 transition hover:border-ink">
                                    <span class="min-w-0">
                                        <span class="block font-semibold">{{ $shop->name }}</span>
                                        <span class="block text-xs text-ink2">{{ $shop->fullAddress() }}</span>
                                        @if($shop->fits_parts_bought_here)
                                            <span class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-fit"><x-storefront.icon name="check" class="h-3.5 w-3.5" /> montează piese cumpărate de la noi</span>
                                        @endif
                                    </span>
                                    <x-storefront.icon name="arrow-right" class="mt-1 h-4 w-4 shrink-0 text-ink2 transition-transform duration-300 group-hover:translate-x-0.5" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="grid gap-2 border-t border-line p-4">
                <a href="{{ $shopsUrl }}" target="_blank" rel="noopener" class="st-btn st-btn--ink st-btn--block">
                    Toate atelierele din director <x-storefront.icon name="arrow-right" class="st-arrow" />
                </a>
                <p class="text-center text-xs text-ink2">Se deschide într-un tab nou; pagina produsului rămâne aici.</p>
            </div>
        </div>
    </div>

    {{-- ============================================================ Buy bar --}}
    <div class="fixed inset-x-0 bottom-0 z-30 translate-y-full border-t border-gl bg-g0/92 text-bone backdrop-blur-xl transition-transform duration-500 ease-[cubic-bezier(.2,.8,.2,1)]"
         :class="bar && 'translate-y-0'" :aria-hidden="bar ? 'false' : 'true'">
        <div class="shell flex h-[4.5rem] items-center gap-4">
            @if($urls->isNotEmpty())
                <img src="{{ $urls->first() }}" alt="" class="h-12 w-12 shrink-0 rounded-[2px] bg-white object-contain p-1 max-sm:hidden">
            @endif

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
                <p class="flex items-center gap-2.5 text-xs text-mute">
                    <span class="font-display text-base font-semibold tabular-nums text-bone">{{ $price ?? 'Preț la cerere' }}</span>
                    @if($vehicle && $verdict->isCertain())
                        <span class="inline-flex items-center gap-1 text-fit-bright">
                            <x-storefront.icon name="check" class="h-3 w-3" /> Se potrivește
                        </span>
                    @elseif($vehicle && ! $unknown && $verdict->fits())
                        <span class="text-sand">Verifică potrivirea</span>
                    @endif
                </p>
            </div>

            <button wire:click="addToCart" @disabled(! $availability->orderable()) :tabindex="bar ? '0' : '-1'" class="st-btn st-btn--sm shrink-0">
                <span wire:loading.remove wire:target="addToCart">Adaugă în coș</span>
                <span wire:loading wire:target="addToCart">Se adaugă…</span>
            </button>
        </div>
    </div>
</div>
