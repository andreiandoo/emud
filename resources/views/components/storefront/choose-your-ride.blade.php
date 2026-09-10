@props([
    'title' => 'Alege-ți mașina',
    'subtitle' => 'Vezi doar piesele și accesoriile care se potrivesc pe ea.',
    'limit' => 12,
])

@php($collections = app(\App\Storefront\CollectionShowcase::class)->featured($limit))

@if($collections->isNotEmpty())
    {{-- A horizontal rail rather than a grid. The list is uneven by nature — a shop carries six
         makes one season and nine the next — and a rail absorbs that without leaving a row of
         holes at the bottom. Scrolling is native so it works with a trackpad, a touchscreen and
         a keyboard without a slider library. --}}
    <section x-data="{
                 rail: null,
                 atStart: true,
                 atEnd: false,
                 init() {
                     this.rail = $refs.rail;
                     this.measure();
                 },
                 measure() {
                     if (! this.rail) return;
                     this.atStart = this.rail.scrollLeft <= 2;
                     this.atEnd = this.rail.scrollLeft + this.rail.clientWidth >= this.rail.scrollWidth - 2;
                 },
                 scrollBy(direction) {
                     this.rail?.scrollBy({ left: direction * Math.max(240, this.rail.clientWidth * 0.8), behavior: 'smooth' });
                 },
             }"
             class="relative">

        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <a href="{{ route('storefront.collections') }}" class="hidden text-sm font-semibold text-stone-700 underline-offset-4 hover:underline sm:inline">
                    Toate colecțiile
                </a>
                <button type="button" @click="scrollBy(-1)" :disabled="atStart"
                        class="hidden h-9 w-9 items-center justify-center rounded-full border border-stone-300 text-stone-700 transition hover:border-stone-900 disabled:opacity-30 sm:flex"
                        aria-label="Înapoi">
                    <x-storefront.icon name="chevron-right" class="h-4 w-4 rotate-180" />
                </button>
                <button type="button" @click="scrollBy(1)" :disabled="atEnd"
                        class="hidden h-9 w-9 items-center justify-center rounded-full border border-stone-300 text-stone-700 transition hover:border-stone-900 disabled:opacity-30 sm:flex"
                        aria-label="Înainte">
                    <x-storefront.icon name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <ul x-ref="rail" @scroll.debounce.100ms="measure()" @resize.window.debounce.200ms="measure()"
            class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0
                   [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($collections as $collection)
                <li class="w-40 shrink-0 snap-start sm:w-48">
                    <a href="{{ $collection->url() }}" class="group block">
                        <div class="relative aspect-square overflow-hidden rounded-2xl bg-stone-200">
                            @if($collection->squareImageUrl())
                                <img src="{{ $collection->squareImageUrl() }}" alt="{{ $collection->name }}"
                                     loading="lazy" width="384" height="384"
                                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <span class="flex h-full w-full items-center justify-center text-stone-400">
                                    <x-storefront.icon name="car" class="h-10 w-10" />
                                </span>
                            @endif
                            <span class="absolute inset-x-0 bottom-0 h-2/3 bg-linear-to-t from-stone-950/70 to-transparent"></span>
                            <span class="absolute inset-x-0 bottom-0 p-3 text-sm font-bold uppercase tracking-wide text-white">
                                {{ $collection->name }}
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <a href="{{ route('storefront.collections') }}" class="mt-3 inline-block text-sm font-semibold text-stone-700 underline-offset-4 hover:underline sm:hidden">
            Toate colecțiile
        </a>
    </section>
@endif
