@php($embed = \App\Support\VideoEmbed::url($collection->video_url))
@php($years = $collection->yearRange())
@php($activeFilters = $this->activeFilterCount())
@php($currency = config('emud.catalog.default_currency', 'RON'))

<div>
    <x-seo :title="$collection->seo_title ?: $collection->name"
           :description="$collection->seo_description ?: $collection->subtitle"
           :canonical="$collection->url()"
           :image="$collection->ogImageUrl()"
           :index="$collection->robots_index"
           :follow="$collection->robots_follow" />

    @push('meta')
        <script type="application/ld+json">{!! $breadcrumbs !!}</script>
    @endpush

    {{-- 1. Hero, edge to edge. A customer arriving from a search should recognise their own car
            before reading a word, so the photograph is the header rather than an illustration
            inside it. The title sits left, over the darkest part of the gradient, which is what
            keeps it readable whatever photograph an operator uploads. --}}
    <section class="relative isolate flex min-h-[22rem] items-end overflow-hidden bg-stone-950 text-white sm:min-h-[26rem] lg:min-h-[32rem]">
        @if($collection->wideImageUrl())
            <img src="{{ $collection->wideImageUrl() }}" alt="{{ $collection->name }}"
                 fetchpriority="high" class="absolute inset-0 -z-10 h-full w-full object-cover">
        @endif

        <div class="absolute inset-0 -z-10 bg-linear-to-r from-stone-950 via-stone-950/80 to-stone-950/20"></div>
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-stone-950/80 via-transparent to-stone-950/40"></div>

        <div class="shell w-full py-10 sm:py-14 lg:py-16">
            <nav class="mb-5 text-xs text-stone-300">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-white">Acasă</a>
                <span class="mx-1.5 text-stone-500">/</span>
                <a href="{{ route('storefront.collections') }}" class="transition hover:text-white">Colecții</a>
                @if($collection->parent)
                    <span class="mx-1.5 text-stone-500">/</span>
                    <a href="{{ $collection->parent->url() }}" class="transition hover:text-white">{{ $collection->parent->name }}</a>
                @endif
                <span class="mx-1.5 text-stone-500">/</span>
                <span class="text-white">{{ $collection->name }}</span>
            </nav>

            <h1 class="max-w-2xl text-4xl font-black uppercase leading-[0.95] tracking-tight sm:text-6xl lg:text-7xl">
                {{ $collection->name }}
            </h1>

            @if($years || $collection->subtitle)
                <p class="mt-4 max-w-xl text-base text-stone-200 sm:text-lg">
                    {{ $collection->subtitle ?: 'Piese și accesorii pentru '.$collection->name }}
                    @if($years)<span class="text-stone-400">· {{ $years }}</span>@endif
                </p>
            @endif

            <div class="mt-6 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold backdrop-blur">
                    <x-storefront.icon name="part" class="h-4 w-4" />
                    {{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}
                </span>

                @if($children->isNotEmpty())
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold backdrop-blur">
                        <x-storefront.icon name="car" class="h-4 w-4" />
                        {{ $children->count() }} {{ $children->count() === 1 ? 'variantă' : 'variante' }}
                    </span>
                @endif
            </div>
        </div>
    </section>

    {{-- 1b. The derivatives, when this collection has any. They sit directly under the hero
             because they are a narrowing of it: someone who knows they drive a 35S18 should not
             have to scroll past two hundred products for the whole make to find their own page. --}}
    @if($children->isNotEmpty())
        <section class="border-b border-stone-200 bg-white">
            <div class="shell py-6">
                <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-stone-500">
                    Variante de {{ $collection->name }}
                </h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach($children as $child)
                        <li>
                            <a href="{{ $child->url() }}"
                               class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3.5 py-1.5 text-sm font-semibold text-stone-700 transition hover:bg-stone-900 hover:text-white">
                                {{ $child->name }}
                                @if($child->yearRange())
                                    <span class="text-xs font-normal text-stone-400">{{ $child->yearRange() }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- 2. The break: the one band between the photograph and the shelves. It carries whatever
            the shop has to say about this car, and collapses to a plain rule when it has
            nothing — an empty band would read as a broken page. --}}
    @if($collection->description || $embed)
        <section class="border-b border-stone-200 bg-white">
            <div class="shell grid gap-8 py-10 lg:grid-cols-2 lg:items-center lg:py-14">
                @if($collection->description)
                    <div class="prose prose-stone max-w-none">
                        {!! nl2br(e($collection->description)) !!}
                    </div>
                @endif

                @if($embed)
                    <div class="aspect-video overflow-hidden rounded-2xl bg-stone-950 {{ $collection->description ? '' : 'lg:col-span-2' }}">
                        <iframe src="{{ $embed }}" title="{{ $collection->name }}" loading="lazy"
                                class="h-full w-full" frameborder="0" allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                @elseif($collection->video_url)
                    <a href="{{ $collection->video_url }}" target="_blank" rel="noopener"
                       class="inline-flex w-fit items-center gap-2 text-sm font-semibold underline underline-offset-4">
                        Vezi materialul video
                    </a>
                @endif
            </div>
        </section>
    @else
        <div class="border-b border-stone-200"></div>
    @endif

    {{-- 3. The listing. One Alpine scope for the mobile filter drawer; the desktop rail is always
            open, so the drawer state never applies to it. --}}
    <section class="shell pt-8" x-data="{ filters: false }" @keydown.escape.window="filters = false">
        <div class="grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
            {{-- Filters. A rail on desktop, a sheet on mobile — the same markup either way, so a
                 filter added here cannot end up in one and not the other. --}}
            <div x-show="filters" x-cloak @click="filters = false"
                 class="fixed inset-0 z-40 bg-stone-950/50 lg:hidden"></div>

            <aside :class="filters ? 'translate-x-0' : '-translate-x-full'"
                   class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] overflow-y-auto bg-white p-5 transition-transform
                          lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:overflow-visible lg:bg-transparent lg:p-0">

                <div class="mb-4 flex items-center justify-between lg:hidden">
                    <span class="text-lg font-bold">Filtre</span>
                    <button type="button" @click="filters = false" class="rounded-lg p-1 text-stone-500 hover:bg-stone-100" aria-label="Închide filtrele">
                        <x-storefront.icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Sticky beside a long grid: scrolling three hundred products should not leave
                     the filters a thousand pixels above the customer. --}}
                <div class="space-y-6 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto lg:pr-1">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-stone-500">Filtrează</h2>
                        @if($activeFilters > 0)
                            <button type="button" wire:click="clearFilters" class="text-xs font-semibold text-stone-600 underline underline-offset-2 hover:text-stone-900">
                                Șterge tot
                            </button>
                        @endif
                    </div>

                    @if($vehicle)
                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl bg-lime-50 p-3 text-sm">
                            <input type="checkbox" wire:model.live="fitsMyVehicle" class="mt-0.5">
                            <span>
                                <span class="block font-semibold">Doar ce se potrivește</span>
                                <span class="block text-xs text-stone-600">{{ $vehicle->label() }}</span>
                            </span>
                        </label>
                    @endif

                    <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                        <input type="checkbox" wire:model.live="inStock">
                        <span>Doar produse pe stoc</span>
                    </label>

                    @if($categoryFacets->isNotEmpty())
                        <div x-data="{ open: true }" class="border-t border-stone-200 pt-5">
                            <button type="button" @click="open = ! open" class="mb-3 flex w-full items-center justify-between text-sm font-bold">
                                Categorie
                                <span class="text-stone-400 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true">▾</span>
                            </button>
                            <ul x-show="open" x-collapse class="space-y-2">
                                @foreach($categoryFacets as $facet)
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                            <input type="checkbox" value="{{ $facet['slug'] }}" wire:model.live="categories">
                                            <span class="min-w-0 flex-1 truncate">{{ $facet['name'] }}</span>
                                            <span class="text-xs tabular-nums text-stone-400">{{ $facet['total'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($brandFacets->isNotEmpty())
                        <div x-data="{ open: true }" class="border-t border-stone-200 pt-5">
                            <button type="button" @click="open = ! open" class="mb-3 flex w-full items-center justify-between text-sm font-bold">
                                Brand
                                <span class="text-stone-400 transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true">▾</span>
                            </button>
                            <ul x-show="open" x-collapse class="space-y-2">
                                @foreach($brandFacets as $facet)
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                            <input type="checkbox" value="{{ $facet['slug'] }}" wire:model.live="brands">
                                            <span class="min-w-0 flex-1 truncate">{{ $facet['name'] }}</span>
                                            <span class="text-xs tabular-nums text-stone-400">{{ $facet['total'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($priceBounds['min'] !== null)
                        <div class="border-t border-stone-200 pt-5">
                            <h3 class="mb-3 text-sm font-bold">Preț</h3>
                            {{-- .live.debounce rather than .live: a price field is typed into
                                 digit by digit, and each keystroke would otherwise be a request
                                 for a range nobody meant to ask for. --}}
                            <div class="flex items-center gap-2">
                                <input type="number" inputmode="numeric" wire:model.live.debounce.600ms="priceMin"
                                       placeholder="{{ (int) floor($priceBounds['min']) }}" aria-label="Preț minim" class="text-sm">
                                <span class="text-stone-400">–</span>
                                <input type="number" inputmode="numeric" wire:model.live.debounce.600ms="priceMax"
                                       placeholder="{{ (int) ceil($priceBounds['max']) }}" aria-label="Preț maxim" class="text-sm">
                            </div>
                            <p class="mt-1.5 text-xs text-stone-500">{{ $currency }}, cu TVA</p>
                        </div>
                    @endif
                </div>

                <button type="button" @click="filters = false" class="mt-6 w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white lg:hidden">
                    Vezi {{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}
                </button>
            </aside>

            <div class="min-w-0">
                {{-- The toolbar sticks to the top of the window. -mx/px so its ground covers the
                     grid gutter as the cards scroll under it, instead of letting a sliver of
                     product show through at the edges. --}}
                <div class="sticky top-0 z-30 -mx-4 mb-5 border-b border-stone-200 bg-stone-50/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="filters = true"
                                class="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold lg:hidden">
                            <x-storefront.icon name="filter" class="h-4 w-4" />
                            Filtre
                            @if($activeFilters > 0)
                                <span class="rounded-full bg-stone-900 px-1.5 text-xs text-white">{{ $activeFilters }}</span>
                            @endif
                        </button>

                        <p class="mr-auto text-sm text-stone-600">
                            <span class="font-semibold text-stone-900">{{ $products->total() }}</span>
                            {{ $products->total() === 1 ? 'produs' : 'produse' }}
                        </p>

                        <label class="shrink-0">
                            <span class="sr-only">Ordonează</span>
                            <select wire:model.live="sort" class="w-auto py-2 text-sm">
                                <option value="relevance">Recomandate</option>
                                <option value="price-asc">Preț crescător</option>
                                <option value="price-desc">Preț descrescător</option>
                                <option value="name">Nume A–Z</option>
                                <option value="newest">Cele mai noi</option>
                            </select>
                        </label>
                    </div>

                    @if($activeFilters > 0)
                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                            @foreach($categories as $slug)
                                <button type="button" wire:click="removeFilter('category', '{{ $slug }}')" wire:key="chip-cat-{{ $slug }}"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-300">
                                    {{ $categoryFacets->firstWhere('slug', $slug)['name'] ?? $slug }} ✕
                                </button>
                            @endforeach

                            @foreach($brands as $slug)
                                <button type="button" wire:click="removeFilter('brand', '{{ $slug }}')" wire:key="chip-brand-{{ $slug }}"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-300">
                                    {{ $brandFacets->firstWhere('slug', $slug)['name'] ?? $slug }} ✕
                                </button>
                            @endforeach

                            @if($priceMin !== '' || $priceMax !== '')
                                <button type="button" wire:click="removeFilter('price')"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-300">
                                    {{ $priceMin !== '' ? $priceMin : '0' }}–{{ $priceMax !== '' ? $priceMax : '∞' }} {{ $currency }} ✕
                                </button>
                            @endif

                            @if($inStock)
                                <button type="button" wire:click="removeFilter('stock')"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-700 transition hover:bg-stone-300">
                                    Pe stoc ✕
                                </button>
                            @endif

                            @if($fitsMyVehicle)
                                <button type="button" wire:click="removeFilter('vehicle')"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-lime-200 px-2.5 py-1 text-xs font-semibold text-lime-900 transition hover:bg-lime-300">
                                    Se potrivește pe mașina mea ✕
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if($products->isEmpty())
                    <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center">
                        @if($activeFilters > 0)
                            <p class="font-semibold text-stone-700">Niciun produs cu filtrele astea.</p>
                            <button type="button" wire:click="clearFilters" class="mt-3 rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">
                                Șterge filtrele
                            </button>
                        @else
                            <p class="font-semibold text-stone-700">Nu avem încă produse listate pentru {{ $collection->name }}.</p>
                            <p class="mt-1 text-sm text-stone-500">
                                <a href="{{ route('storefront.contact') }}" class="font-semibold underline">Scrie-ne ce cauți</a> și îți spunem dacă putem aduce.
                            </p>
                        @endif
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach($products as $product)
                            @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $verdicts($product)])
                        @endforeach
                    </div>

                    <div class="mt-8">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </section>

    @if($reviews->isNotEmpty())
        <section class="shell mt-16">
            <h2 class="mb-5 text-xl font-bold">Ce spun cei care conduc {{ $collection->name }}</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($reviews as $review)
                    <x-storefront.review-card :review="$review" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="shell mt-16">
        <div class="rounded-2xl bg-stone-100 p-6 sm:p-8">
            <h2 class="text-lg font-bold">Ai un {{ $collection->name }}?</h2>
            <p class="mt-1 text-sm text-stone-600">
                Salvează-l în garaj și îți arătăm doar ce se potrivește pe el, de fiecare dată când intri.
            </p>
            <a href="{{ route('customer.garage') }}" class="mt-4 inline-flex rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
                Adaugă în garaj
            </a>
        </div>
    </section>
</div>
