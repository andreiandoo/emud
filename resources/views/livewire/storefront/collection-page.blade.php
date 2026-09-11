@php($embed = \App\Support\VideoEmbed::url($collection->video_url))
@php($years = $collection->yearRange())
@php($activeFilters = $this->activeFilterCount())
@php($currency = config('emud.catalog.default_currency', 'RON'))

<div>
    <x-seo :title="$collection->seo_title ?: $collection->name"
           :description="$collection->seo_description ?: $collection->subtitle"
           :canonical="$collection->url()"
           :image="$collection->ogImageUrl()"
           {{-- A collection with nothing in it is thin content, and eleven thousand thin pages
                drag down the ones that are not. It goes back in the index the moment a product
                lands in it; the flag can only ever take a page out, never force one in. --}}
           :index="$collection->robots_index && $products->total() > 0"
           :follow="$collection->robots_follow" />

    @push('meta')
        <script type="application/ld+json">{!! $breadcrumbs !!}</script>
    @endpush

    {{-- 1. Hero, edge to edge. A customer arriving from a search should recognise their own car
            before reading a word, so the photograph is the header rather than an illustration
            inside it. The title sits left, over the darkest part of the gradient, which is what
            keeps it readable whatever photograph an operator uploads. --}}
    <section class="relative isolate flex min-h-[26rem] items-end overflow-hidden bg-g0 text-bone sm:min-h-[32rem] lg:min-h-[38rem]">
        @if($collection->wideImageUrl())
            <img src="{{ $collection->wideImageUrl() }}" alt="{{ $collection->name }}"
                 fetchpriority="high" class="absolute inset-0 -z-20 h-full w-full object-cover" data-st-parallax>
        @else
            <canvas data-st-scene="dusk" data-seed="{{ $collection->id }}" class="absolute inset-0 -z-20 h-full w-full" aria-hidden="true"></canvas>
        @endif

        <div class="absolute inset-0 -z-10 bg-linear-to-r from-g0 via-g0/75 to-g0/10"></div>
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-g0 via-transparent to-g0/50"></div>

        <div class="shell w-full pb-12 pt-[calc(var(--st-header-h)+2rem)] sm:pb-16 lg:pb-20">
            <nav class="mb-6 flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.collections') }}" class="transition hover:text-bone">Colecții</a>
                @if($collection->parent)
                    <span aria-hidden="true">/</span>
                    <a href="{{ $collection->parent->url() }}" class="transition hover:text-bone">{{ $collection->parent->name }}</a>
                @endif
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $collection->name }}</span>
            </nav>

            <h1 class="st-display max-w-4xl text-[clamp(2.9rem,7vw,7rem)] leading-[.9]">{{ $collection->name }}</h1>

            @if($years || $collection->subtitle)
                <p class="mt-5 max-w-xl text-[clamp(1rem,1.3vw,1.2rem)] text-[#d8d6cf]">
                    {{ $collection->subtitle ?: 'Piese și accesorii pentru '.$collection->name }}
                    @if($years)<span class="text-mute">· {{ $years }}</span>@endif
                </p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-2.5">
                <span class="inline-flex h-11 items-center gap-2 rounded-full border border-white/20 bg-g0/40 px-4 text-sm font-semibold backdrop-blur">
                    <x-storefront.icon name="part" class="h-4 w-4 text-sand" />
                    {{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}
                </span>

                @if($children->isNotEmpty())
                    {{-- Dispatched on the window rather than toggled here: the dialog belongs to
                         the finder below, and the hero should not have to know where it is. --}}
                    <button type="button" @click="$dispatch('open-variant-chooser')" class="st-btn st-btn--sm">
                        <x-storefront.icon name="car" />
                        {{ $children->count() }} {{ $children->count() === 1 ? 'variantă' : 'variante' }}
                        <span class="font-normal normal-case tracking-normal opacity-70">· alege-o pe a ta</span>
                    </button>
                @endif
            </div>
        </div>
    </section>

    {{-- The bar: three ways in — pick the car, decode a VIN, choose a derivative. --}}
    <livewire:storefront.vehicle-finder :collection="$collection" />

    {{-- 2. The break: the one band between the photograph and the shelves. It carries whatever
            the shop has to say about this car, and collapses when it has nothing — an empty band
            would read as a broken page. --}}
    @if($collection->description || $embed)
        <section class="bg-white">
            <div class="shell grid gap-10 py-14 lg:grid-cols-2 lg:items-center lg:py-20">
                @if($collection->description)
                    <div class="grid gap-5">
                        <p class="st-kicker text-ink2">Despre {{ $collection->name }}</p>
                        <div class="max-w-[62ch] text-[16.5px] leading-relaxed text-ink2">
                            {!! nl2br(e($collection->description)) !!}
                        </div>
                    </div>
                @endif

                @if($embed)
                    <div class="aspect-video overflow-hidden rounded-[3px] bg-g0 {{ $collection->description ? '' : 'lg:col-span-2' }}">
                        <iframe src="{{ $embed }}" title="{{ $collection->name }}" loading="lazy"
                                class="h-full w-full" frameborder="0" allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                @elseif($collection->video_url)
                    <a href="{{ $collection->video_url }}" target="_blank" rel="noopener" class="st-link w-fit">
                        <x-storefront.icon name="play" /> Vezi materialul video
                    </a>
                @endif
            </div>
        </section>
    @endif

    {{-- 3. The listing. One Alpine scope for the mobile filter drawer; the desktop rail is always
            open, so the drawer state never applies to it. --}}
    <section class="shell pb-8 pt-10" x-data="{ filters: false }" @keydown.escape.window="filters = false">
        <div class="grid gap-10 lg:grid-cols-[16.5rem_minmax(0,1fr)]">
            {{-- Filters. A rail on desktop, a sheet on mobile — the same markup either way, so a
                 filter added here cannot end up in one and not the other. --}}
            <div x-show="filters" x-cloak @click="filters = false" class="fixed inset-0 z-40 bg-g0/60 backdrop-blur-sm lg:hidden"></div>

            <aside :class="filters ? 'translate-x-0' : '-translate-x-full'" data-lenis-prevent
                   class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] overflow-y-auto bg-light p-6 transition-transform duration-500 ease-[cubic-bezier(.2,.8,.2,1)]
                          lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:overflow-visible lg:bg-transparent lg:p-0">

                <div class="mb-5 flex items-center justify-between lg:hidden">
                    <span class="font-display text-2xl font-semibold">Filtre</span>
                    <button type="button" @click="filters = false" class="grid h-10 w-10 place-items-center rounded-full border border-line2" aria-label="Închide filtrele">
                        <x-storefront.icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Sticky beside a long grid: scrolling three hundred products should not leave
                     the filters a thousand pixels above the customer. --}}
                <div class="grid gap-6 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:pr-2" data-lenis-prevent>
                    <div class="flex items-center justify-between">
                        <h2 class="st-kicker text-ink2">Filtrează</h2>
                        @if($activeFilters > 0)
                            <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-ink2 underline underline-offset-2 hover:text-ink">
                                Șterge tot
                            </button>
                        @endif
                    </div>

                    @if($vehicle)
                        <label class="flex cursor-pointer items-start gap-3 rounded-[3px] border border-fit/30 bg-fit/[.06] p-3.5 text-sm">
                            <input type="checkbox" wire:model.live="fitsMyVehicle" class="mt-0.5">
                            <span>
                                <span class="block font-semibold">Doar ce se potrivește</span>
                                <span class="block text-xs text-ink2">{{ $vehicle->label() }}</span>
                            </span>
                        </label>
                    @endif

                    <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                        <input type="checkbox" wire:model.live="inStock">
                        <span>Doar produse pe stoc</span>
                    </label>

                    @foreach([['Categorie', $categoryFacets, 'categories'], ['Brand', $brandFacets, 'brands']] as [$facetTitle, $facets, $model])
                        @if($facets->isNotEmpty())
                            <div x-data="{ open: true }" class="border-t border-line pt-5">
                                <button type="button" @click="open = ! open" class="mb-3 flex w-full items-center justify-between font-display text-[15px] font-semibold">
                                    {{ $facetTitle }}
                                    <x-storefront.icon name="chevron-down" class="h-4 w-4 text-ink2 transition-transform duration-300" ::class="open && 'rotate-180'" />
                                </button>
                                <ul x-show="open" x-collapse class="grid gap-2">
                                    @foreach($facets as $facet)
                                        <li>
                                            <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                                <input type="checkbox" value="{{ $facet['slug'] }}" wire:model.live="{{ $model }}">
                                                <span class="min-w-0 flex-1 truncate">{{ $facet['name'] }}</span>
                                                <span class="font-mono text-xs tabular-nums text-ink2">{{ $facet['total'] }}</span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach

                    @if($priceBounds['min'] !== null)
                        <div class="border-t border-line pt-5">
                            <h3 class="mb-3 font-display text-[15px] font-semibold">Preț</h3>
                            {{-- .live.debounce rather than .live: a price field is typed into digit
                                 by digit, and each keystroke would otherwise be a request for a
                                 range nobody meant to ask for. --}}
                            <div class="flex items-center gap-2">
                                <input type="number" inputmode="numeric" wire:model.live.debounce.600ms="priceMin"
                                       placeholder="{{ (int) floor($priceBounds['min']) }}" aria-label="Preț minim">
                                <span class="text-ink2">–</span>
                                <input type="number" inputmode="numeric" wire:model.live.debounce.600ms="priceMax"
                                       placeholder="{{ (int) ceil($priceBounds['max']) }}" aria-label="Preț maxim">
                            </div>
                            <p class="mt-1.5 text-xs text-ink2">{{ $currency }}, cu TVA</p>
                        </div>
                    @endif
                </div>

                <button type="button" @click="filters = false" class="st-btn st-btn--ink st-btn--block mt-6 lg:hidden">
                    Vezi {{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}
                </button>
            </aside>

            <div class="min-w-0">
                {{-- The toolbar sticks to the top of the window. -mx/px so its ground covers the
                     grid gutter as the cards scroll under it. --}}
                <div class="sticky top-0 z-30 -mx-[var(--st-pad)] mb-6 border-b border-line bg-light/95 px-[var(--st-pad)] py-3 backdrop-blur lg:mx-0 lg:px-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="filters = true" class="st-btn st-btn--outline st-btn--sm lg:hidden">
                            <x-storefront.icon name="filter" />
                            Filtre
                            @if($activeFilters > 0)
                                <span class="rounded-full bg-ink px-1.5 text-[11px] text-light">{{ $activeFilters }}</span>
                            @endif
                        </button>

                        <p class="mr-auto text-sm text-ink2">
                            <span class="font-display text-lg font-semibold text-ink">{{ $products->total() }}</span>
                            {{ $products->total() === 1 ? 'produs' : 'produse' }}
                        </p>

                        <label class="shrink-0">
                            <span class="sr-only">Ordonează</span>
                            <select wire:model.live="sort" class="min-h-10 w-auto py-2 text-sm">
                                <option value="relevance">Recomandate</option>
                                <option value="price-asc">Preț crescător</option>
                                <option value="price-desc">Preț descrescător</option>
                                <option value="name">Nume A–Z</option>
                                <option value="newest">Cele mai noi</option>
                            </select>
                        </label>
                    </div>

                    @if($activeFilters > 0)
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @foreach($categories as $slug)
                                <button type="button" wire:click="removeFilter('category', '{{ $slug }}')" wire:key="chip-cat-{{ $slug }}" class="st-chip h-8 bg-white text-xs hover:border-ink">
                                    {{ $categoryFacets->firstWhere('slug', $slug)['name'] ?? $slug }} <x-storefront.icon name="close" class="h-3 w-3" />
                                </button>
                            @endforeach

                            @foreach($brands as $slug)
                                <button type="button" wire:click="removeFilter('brand', '{{ $slug }}')" wire:key="chip-brand-{{ $slug }}" class="st-chip h-8 bg-white text-xs hover:border-ink">
                                    {{ $brandFacets->firstWhere('slug', $slug)['name'] ?? $slug }} <x-storefront.icon name="close" class="h-3 w-3" />
                                </button>
                            @endforeach

                            @if($priceMin !== '' || $priceMax !== '')
                                <button type="button" wire:click="removeFilter('price')" class="st-chip h-8 bg-white text-xs hover:border-ink">
                                    {{ $priceMin !== '' ? $priceMin : '0' }}–{{ $priceMax !== '' ? $priceMax : '∞' }} {{ $currency }} <x-storefront.icon name="close" class="h-3 w-3" />
                                </button>
                            @endif

                            @if($inStock)
                                <button type="button" wire:click="removeFilter('stock')" class="st-chip h-8 bg-white text-xs hover:border-ink">
                                    Pe stoc <x-storefront.icon name="close" class="h-3 w-3" />
                                </button>
                            @endif

                            @if($fitsMyVehicle && $vehicle)
                                <button type="button" wire:click="removeFilter('vehicle')" class="st-chip h-8 border-fit/40 bg-fit/[.08] text-xs text-fit hover:border-fit">
                                    Se potrivește pe mașina mea <x-storefront.icon name="close" class="h-3 w-3" />
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if($products->isEmpty())
                    <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                        @if($activeFilters > 0)
                            <p class="font-display text-xl font-semibold">Niciun produs cu filtrele astea.</p>
                            <button type="button" wire:click="clearFilters" class="st-btn st-btn--ink st-btn--sm">Șterge filtrele</button>
                        @else
                            <p class="font-display text-xl font-semibold">Nu avem încă produse listate pentru {{ $collection->name }}.</p>
                            <p class="text-sm text-ink2">
                                <a href="{{ route('storefront.contact') }}" class="font-semibold text-ink underline underline-offset-2">Scrie-ne ce cauți</a> și îți spunem dacă putem aduce.
                            </p>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach($products as $product)
                            @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $verdicts($product)])
                        @endforeach
                    </div>

                    <div class="mt-10">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </section>

    @if($reviews->isNotEmpty())
        <section class="shell mt-16">
            <div class="mb-8 grid gap-4">
                <p class="st-kicker text-ink2">Recenzii</p>
                <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Ce spun cei care conduc {{ $collection->name }}</h2>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($reviews as $review)
                    <x-storefront.review-card :review="$review" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-20 bg-sand text-ink">
        <div class="shell grid gap-6 py-14 sm:py-16 lg:grid-cols-[1.4fr_auto] lg:items-center">
            <div class="grid gap-3">
                <h2 class="st-display text-[clamp(1.9rem,3.2vw,3rem)]">Ai un {{ $collection->name }}?</h2>
                <p class="max-w-[56ch] text-sandink">
                    Salvează-l în garaj și îți arătăm doar ce se potrivește pe el, de fiecare dată când intri.
                </p>
            </div>
            <a href="{{ route('customer.garage') }}" class="st-btn st-btn--ink w-fit">Adaugă în garaj <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
        </div>
    </section>
</div>
