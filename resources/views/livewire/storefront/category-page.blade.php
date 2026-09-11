@php($currency = config('emud.catalog.default_currency', 'RON'))
@php($total = $products->total())
@php($specNames = $specFacets->pluck('name', 'code'))
@php($specLabels = $specFacets->mapWithKeys(fn (array $facet) => [$facet['code'] => collect($facet['values'])->pluck('label', 'value')->all()]))

<div>
    {{-- Filtered and paginated views are the same catalogue sliced differently, so the canonical
         always points at the unfiltered first page. --}}
    <x-seo :title="$category->name"
           :description="$category->description ?? 'Piese și accesorii 4x4 din categoria '.$category->name"
           :canonical="route('storefront.category', $category)" />

    {{-- 1. Hero. --}}
    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        @if($category->image_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt=""
                 class="absolute inset-0 -z-20 h-full w-full object-cover opacity-45">
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-g0 via-g0/80 to-g0/30"></div>
        @else
            <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        @endif

        <div class="shell pb-12 pt-14 sm:pb-14 sm:pt-20">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                @foreach($ancestors as $ancestor)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('storefront.category', $ancestor) }}" class="transition hover:text-bone">{{ $ancestor->name }}</a>
                @endforeach
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $category->name }}</span>
            </nav>

            <h1 class="st-display mt-6 max-w-4xl text-[clamp(2.4rem,5.6vw,5.25rem)] leading-[.92]">{{ $category->name }}</h1>
            @if($category->description)
                <p class="mt-5 max-w-2xl text-[#cfcdc6]">{{ $category->description }}</p>
            @endif
        </div>
    </section>

    {{-- 2. Three ways in: the car, its VIN, or a narrower corner of the catalogue. --}}
    <livewire:storefront.vehicle-finder :category="$category" :key="'finder-'.$category->id" />

    {{-- 3. Filters and listing. The bar is one line and stays under the header; everything else
            waits behind "Filtre" until it is asked for. --}}
    <section x-data="{ filters: false }" @keydown.escape.window="filters = false">
        <div x-show="filters" x-cloak x-transition.opacity.duration.400ms @click="filters = false"
             class="fixed inset-0 z-20 bg-g0/35" aria-hidden="true"></div>

        <div class="st-filterbar">
            <div class="shell flex h-[3.75rem] items-center gap-2.5 sm:gap-3">
                <button type="button" @click="filters = ! filters" aria-controls="st-filters" :aria-expanded="filters ? 'true' : 'false'"
                        class="inline-flex h-10 shrink-0 items-center gap-2.5 rounded-full bg-ink pl-4 pr-3.5 text-[13px] font-semibold text-light transition-colors hover:bg-[#2a2c31]">
                    <x-storefront.icon name="sliders" class="h-4 w-4" />
                    Filtre
                    @if($activeFilters > 0)
                        <span class="grid h-5 min-w-5 place-items-center rounded-full bg-signal px-1 font-mono text-[11px] text-ink">{{ $activeFilters }}</span>
                    @endif
                    <x-storefront.icon name="chevron-down" class="h-3.5 w-3.5 transition-transform duration-500" ::class="filters && 'rotate-180'" />
                </button>

                @if($vehicle)
                    <label class="inline-flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-full border border-line2 px-3.5 text-[13px] text-ink2 transition has-[:checked]:border-fit/40 has-[:checked]:bg-fit/[.08] has-[:checked]:text-fit max-md:hidden">
                        <input type="checkbox" wire:model.live="onlyForMyVehicle" class="sr-only">
                        <x-storefront.icon name="check" class="h-3.5 w-3.5" />
                        <span class="max-w-48 truncate">Doar pentru {{ $vehicle->label() }}</span>
                    </label>
                @endif

                {{-- What is applied, one tap to remove each. Scrolls sideways rather than wrapping,
                     so the bar never grows a second line. --}}
                <div class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto [scrollbar-width:none] max-md:hidden">
                    @foreach($subcategories as $slug)
                        <button type="button" wire:click="removeFilter('subcategory', @js($slug))" wire:key="chip-sub-{{ $slug }}" class="st-chip h-8 shrink-0 bg-white text-xs hover:border-ink">
                            {{ $subcategoryFacets->firstWhere('slug', $slug)['name'] ?? $slug }} <x-storefront.icon name="close" class="h-3 w-3" />
                        </button>
                    @endforeach

                    @foreach($brands as $slug)
                        <button type="button" wire:click="removeFilter('brand', @js($slug))" wire:key="chip-brand-{{ $slug }}" class="st-chip h-8 shrink-0 bg-white text-xs hover:border-ink">
                            {{ $brandFacets->firstWhere('slug', $slug)['name'] ?? $slug }} <x-storefront.icon name="close" class="h-3 w-3" />
                        </button>
                    @endforeach

                    @if($priceMin !== '' || $priceMax !== '')
                        <button type="button" wire:click="removeFilter('price')" class="st-chip h-8 shrink-0 bg-white text-xs hover:border-ink">
                            {{ $priceMin !== '' ? $priceMin : '0' }}–{{ $priceMax !== '' ? $priceMax : '∞' }} {{ $currency }} <x-storefront.icon name="close" class="h-3 w-3" />
                        </button>
                    @endif

                    @if($inStock)
                        <button type="button" wire:click="removeFilter('stock')" class="st-chip h-8 shrink-0 bg-white text-xs hover:border-ink">
                            Pe stoc <x-storefront.icon name="close" class="h-3 w-3" />
                        </button>
                    @endif

                    @foreach($chosenSpecs as $code => $values)
                        @foreach($values as $value)
                            <button type="button" wire:click="removeFilter('spec', @js($value), @js($code))" wire:key="chip-spec-{{ md5($code.'|'.$value) }}" class="st-chip h-8 shrink-0 bg-white text-xs hover:border-ink">
                                <span class="text-ink2">{{ $specNames[$code] ?? $code }}:</span> {{ $specLabels[$code][$value] ?? substr($value, 1) }}
                                <x-storefront.icon name="close" class="h-3 w-3" />
                            </button>
                        @endforeach
                    @endforeach

                    @if($activeFilters > 1)
                        <button type="button" wire:click="clearFilters" class="shrink-0 px-1 text-xs font-semibold text-ink2 underline underline-offset-2 hover:text-ink">Șterge tot</button>
                    @endif
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-4">
                    <p class="text-sm text-ink2 max-lg:hidden">
                        <span class="font-display text-lg font-semibold tabular-nums text-ink">{{ $total }}</span> {{ $total === 1 ? 'produs' : 'produse' }}
                    </p>

                    <label>
                        <span class="sr-only">Ordonează</span>
                        <select wire:model.live="sort" class="h-10 min-h-10 w-auto rounded-full py-0 pl-4 pr-9 text-[13px]">
                            <option value="relevance">Recomandate</option>
                            <option value="price-asc">Preț crescător</option>
                            <option value="price-desc">Preț descrescător</option>
                            <option value="name">Nume A–Z</option>
                            <option value="newest">Cele mai noi</option>
                        </select>
                    </label>
                </div>
            </div>

            {{-- Every filter this category has, dropped over the grid. Each tick updates the list
                 behind it straight away, so "Vezi N produse" is a count, not a promise. --}}
            <div id="st-filters" class="st-filterpanel" :class="filters && 'is-open'">
                <div class="shell max-h-[min(72vh,46rem)] overflow-y-auto py-8" data-lenis-prevent>
                    <div class="grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-4">
                        {{-- Fit and stock first: the two questions every customer has. --}}
                        <div class="grid content-start gap-3">
                            <h3 class="st-kicker text-ink2">Potrivire și stoc</h3>

                            @if($vehicle)
                                <label class="flex cursor-pointer items-start gap-3 rounded-[3px] border border-fit/30 bg-fit/[.06] p-3.5 text-sm">
                                    <input type="checkbox" wire:model.live="onlyForMyVehicle" class="mt-0.5">
                                    <span>
                                        <span class="block font-semibold">Doar ce se potrivește</span>
                                        <span class="block text-xs text-ink2">{{ $vehicle->label() }}</span>
                                    </span>
                                </label>
                            @else
                                <button type="button" @click="filters = false; $dispatch('open-vehicle-selector', { tab: 'car' })"
                                        class="flex items-center gap-3 rounded-[3px] border border-dashed border-line2 p-3.5 text-left text-sm transition hover:border-ink">
                                    <x-storefront.icon name="car" class="h-5 w-5 shrink-0" />
                                    <span>
                                        <span class="block font-semibold">Alege-ți mașina</span>
                                        <span class="block text-xs text-ink2">și îți arătăm doar piesele compatibile</span>
                                    </span>
                                </button>
                            @endif

                            <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                <input type="checkbox" wire:model.live="inStock">
                                <span>Doar produse pe stoc</span>
                            </label>
                        </div>

                        @if($priceBounds['min'] !== null)
                            <div class="grid content-start gap-3">
                                <h3 class="st-kicker text-ink2">Preț</h3>
                                {{-- Debounced: a price is typed digit by digit, and each keystroke
                                     would otherwise ask for a range nobody meant. --}}
                                <div class="flex items-center gap-2">
                                    <input type="number" inputmode="numeric" min="0" wire:model.live.debounce.600ms="priceMin"
                                           placeholder="{{ (int) floor($priceBounds['min']) }}" aria-label="Preț minim">
                                    <span class="text-ink2">–</span>
                                    <input type="number" inputmode="numeric" min="0" wire:model.live.debounce.600ms="priceMax"
                                           placeholder="{{ (int) ceil($priceBounds['max']) }}" aria-label="Preț maxim">
                                </div>
                                <p class="text-xs text-ink2">{{ $currency }}, cu TVA</p>
                            </div>
                        @endif

                        @foreach([['Subcategorie', $subcategoryFacets, 'subcategories', $subcategories], ['Marcă', $brandFacets, 'brands', $brands]] as [$facetTitle, $facets, $model, $selected])
                            @if($facets->isNotEmpty())
                                <div class="grid content-start gap-3" x-data="{ more: false }" wire:key="facet-{{ $model }}">
                                    <h3 class="st-kicker text-ink2">{{ $facetTitle }}</h3>
                                    <ul class="grid gap-2">
                                        @foreach($facets as $facet)
                                            <li wire:key="facet-{{ $model }}-{{ $facet['slug'] }}"
                                                @if($loop->index >= 8 && ! in_array($facet['slug'], $selected, true)) x-show="more" x-cloak @endif>
                                                <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                                    <input type="checkbox" value="{{ $facet['slug'] }}" wire:model.live="{{ $model }}">
                                                    <span class="min-w-0 flex-1 truncate">{{ $facet['name'] }}</span>
                                                    <span class="font-mono text-xs tabular-nums text-ink2">{{ $facet['total'] }}</span>
                                                </label>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($facets->count() > 8)
                                        <button type="button" @click="more = ! more" class="w-fit text-xs font-semibold text-ink2 underline underline-offset-2 hover:text-ink">
                                            <span x-show="! more">Încă {{ $facets->count() - 8 }}</span>
                                            <span x-show="more" x-cloak>Mai puține</span>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        {{-- The specification filters an operator marked on this part of the tree:
                             lift height, diameter, material. --}}
                        @foreach($specFacets as $facet)
                            @php($ticked = $chosenSpecs[$facet['code']] ?? [])
                            <div class="grid content-start gap-3" x-data="{ more: false }" wire:key="spec-{{ $facet['code'] }}">
                                <h3 class="st-kicker text-ink2">{{ $facet['name'] }}</h3>
                                <ul class="grid gap-2">
                                    @foreach($facet['values'] as $option)
                                        @php($isTicked = in_array($option['value'], $ticked, true))
                                        {{-- Keyed on its state too: a box unticked from the summary
                                             row is then redrawn rather than left showing its old tick. --}}
                                        <li wire:key="spec-{{ md5($facet['code'].'|'.$option['value']) }}-{{ $isTicked ? 1 : 0 }}"
                                            @if($loop->index >= 8 && ! $isTicked) x-show="more" x-cloak @endif>
                                            <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                                                <input type="checkbox" @checked($isTicked) wire:click="toggleSpec(@js($facet['code']), @js($option['value']))">
                                                <span class="min-w-0 flex-1 truncate">{{ $option['label'] }}</span>
                                                <span class="font-mono text-xs tabular-nums text-ink2">{{ $option['total'] }}</span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                                @if(count($facet['values']) > 8)
                                    <button type="button" @click="more = ! more" class="w-fit text-xs font-semibold text-ink2 underline underline-offset-2 hover:text-ink">
                                        <span x-show="! more">Încă {{ count($facet['values']) - 8 }}</span>
                                        <span x-show="more" x-cloak>Mai puține</span>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-9 flex flex-wrap items-center justify-between gap-4 border-t border-line pt-5">
                        @if($activeFilters > 0)
                            <button type="button" wire:click="clearFilters" class="text-sm font-semibold text-ink2 underline underline-offset-2 hover:text-ink">Șterge toate filtrele</button>
                        @else
                            <p class="text-sm text-ink2">Bifează ce te interesează; lista se actualizează pe loc.</p>
                        @endif

                        <button type="button" @click="filters = false" class="st-btn st-btn--ink st-btn--sm">
                            Vezi {{ $total }} {{ $total === 1 ? 'produs' : 'produse' }} <x-storefront.icon name="arrow-right" class="st-arrow" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="shell pb-24 pt-8">
            @if($products->isEmpty())
                <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                    <x-storefront.icon name="part" class="h-10 w-10 text-line2" />
                    <p class="max-w-lg font-display text-xl font-semibold">
                        @if($activeFilters > 0)
                            Niciun produs cu filtrele alese.
                        @elseif($vehicle && $onlyForMyVehicle)
                            Niciun produs din această categorie nu este marcat compatibil cu {{ $vehicle->label() }}.
                        @else
                            Nu există încă produse publicate în această categorie.
                        @endif
                    </p>
                    @if($activeFilters > 0)
                        <button type="button" wire:click="clearFilters" class="st-btn st-btn--ink st-btn--sm">Șterge filtrele</button>
                    @elseif($vehicle && $onlyForMyVehicle)
                        <button type="button" wire:click="$set('onlyForMyVehicle', false)" class="st-btn st-btn--outline st-btn--sm">Arată toată categoria</button>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($products as $product)
                        @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $vehicle ? $verdicts($product) : null])
                    @endforeach
                </div>

                <div class="mt-10">{{ $products->links() }}</div>
            @endif
        </div>
    </section>
</div>
