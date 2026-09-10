{{-- Literal class strings, not built from a variable: Tailwind scans this file for the names it
     must generate, and a class assembled at render time is never in the stylesheet. --}}
@php($shades = ['bg-stone-900', 'bg-stone-800', 'bg-stone-950'])

<div class="bg-stone-950">
    <x-seo title="Colecții pe model de mașină"
           description="Caută-ți mașina și vezi tot ce ținem pentru ea: piese, accesorii off-road și echipare."
           :canonical="route('storefront.collections')" />

    {{-- The search box is the page. A visitor arrives knowing exactly what they drive, so the
         one thing worth putting in front of them is a box to type it into — everything else here
         is there to make that box feel like it is backed by something. --}}
    <section class="relative isolate overflow-hidden border-b border-white/10 text-white">
        <div class="absolute -top-40 left-1/2 -z-10 h-[28rem] w-[28rem] -translate-x-1/2 rounded-full bg-stone-700/30 blur-3xl"></div>
        <div class="absolute -bottom-52 right-0 -z-10 h-[24rem] w-[24rem] rounded-full bg-lime-500/10 blur-3xl"></div>

        <div class="shell py-14 sm:py-20">
            <h1 class="text-center text-[11px] font-semibold uppercase tracking-[.35em] text-stone-400">Colecții</h1>

            <div class="mx-auto mt-6 max-w-2xl">
                <label class="relative block">
                    <span class="sr-only">Caută marca sau modelul mașinii</span>

                    <span class="pointer-events-none absolute inset-y-0 left-5 flex items-center text-stone-400">
                        <x-storefront.icon name="search" class="h-5 w-5" />
                    </span>

                    <input type="search" wire:model.live.debounce.300ms="search"
                           placeholder="Scrie marca sau modelul — Dacia, Jimny, Hilux…"
                           autocomplete="off"
                           class="h-16 w-full rounded-full border-white/15 bg-white/5 pl-14 pr-14 text-base text-white
                                  placeholder:text-stone-500 focus:border-white/40 focus:bg-white/10 focus:ring-white/30
                                  sm:text-lg">

                    <span class="absolute inset-y-0 right-5 flex items-center">
                        <span wire:loading wire:target="search" class="h-4 w-4 animate-spin rounded-full border-2 border-stone-600 border-t-white"></span>
                        @if($search !== '')
                            <button type="button" wire:click="clearSearch" wire:loading.remove wire:target="search"
                                    class="text-stone-400 transition hover:text-white" aria-label="Șterge căutarea">
                                <x-storefront.icon name="close" class="h-5 w-5" />
                            </button>
                        @endif
                    </span>
                </label>

                <p class="mt-3 text-center text-sm text-stone-400" aria-live="polite">
                    @if($search !== '')
                        <span class="font-semibold text-white">{{ $total }}</span>
                        {{ $total === 1 ? 'colecție găsită' : 'colecții găsite' }} pentru „{{ $search }}”
                    @else
                        Sau alege din colecțiile de mai jos.
                    @endif
                </p>
            </div>

            {{-- The numbers are here to answer "do they actually have anything for my car" before
                 the visitor has scrolled far enough to find out for themselves. --}}
            {{-- Flex rather than a grid: a metric that counts zero is dropped upstream, so the
                 number of cells is not known when the classes are written and a grid would leave
                 a hole where the missing one was. --}}
            @if($metrics !== [])
                <dl class="mx-auto mt-12 flex max-w-4xl flex-wrap justify-center gap-y-8">
                    @foreach($metrics as $metric)
                        {{-- flex-col-reverse rather than reordering the markup: a <dl> wants its
                             <dt> before its <dd>, and the number belongs on top. --}}
                        <div class="flex w-1/2 flex-col-reverse px-3 text-center sm:w-auto sm:px-8
                                    sm:border-l sm:border-white/10 sm:first:border-l-0">
                            <dt class="mt-1.5 text-[11px] uppercase tracking-wider text-stone-400">{{ $metric['label'] }}</dt>
                            <dd class="text-3xl font-black tabular-nums sm:text-4xl">
                                {{ number_format($metric['value'], 0, ',', '.') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>

    {{-- The wall. No gaps: the tiles are meant to read as one surface, and a grid gutter here
         turns a wall of cars into a spreadsheet of cars. --}}
    @if($tiles->isEmpty())
        {{-- Two different silences. "Nothing matched" is a dead end the visitor can back out of;
             "there is nothing here yet" is the shop's problem, and offering to clear a search
             that was never typed would just be confusing. --}}
        <section class="shell py-24 text-center text-white">
            @if($search !== '')
                <p class="text-lg font-semibold">Nicio colecție pentru „{{ $search }}”.</p>
                <p class="mt-2 text-sm text-stone-400">Încearcă doar marca, fără an sau motorizare.</p>
                <button type="button" wire:click="clearSearch" class="mt-6 rounded-full border border-white/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    Vezi toate colecțiile
                </button>
            @else
                <p class="text-lg font-semibold">Încă nu am publicat nicio colecție.</p>
                <p class="mt-2 text-sm text-stone-400">Spune-ne ce mașină ai și îți facem una.</p>
                <a href="{{ route('storefront.contact') }}" class="mt-6 inline-flex rounded-full border border-white/20 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                    Scrie-ne
                </a>
            @endif
        </section>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
            @foreach($tiles as $tile)
                <a href="{{ $tile->url() }}" wire:key="tile-{{ $tile->id }}"
                   class="group relative isolate aspect-square overflow-hidden {{ $tile->squareImageUrl() ? 'bg-stone-900' : $shades[$loop->index % 3] }}">

                    @if($tile->squareImageUrl())
                        <img src="{{ $tile->squareImageUrl() }}" alt="{{ $tile->name }}"
                             loading="lazy" width="480" height="480"
                             class="absolute inset-0 -z-10 h-full w-full object-cover opacity-80 transition duration-500
                                    group-hover:scale-105 group-hover:opacity-100">
                    @endif

                    {{-- Two overlays: one to keep the name legible over any photograph, one that
                         lights the tile up on hover rather than merely moving it. Both sit at
                         -z-10, which paints above the tile's own background but below the name
                         because the tile is an isolated stacking context. --}}
                    <span class="absolute inset-0 -z-10 bg-linear-to-t from-stone-950/90 via-stone-950/20 to-transparent"></span>
                    <span class="absolute inset-0 -z-10 ring-1 ring-inset ring-white/0 transition duration-300 group-hover:bg-white/5 group-hover:ring-white/30"></span>

                    <span class="absolute inset-x-0 bottom-0 p-3 sm:p-4">
                        <span class="block text-sm font-bold uppercase leading-tight tracking-wide text-white sm:text-base">
                            {{ $tile->name }}
                        </span>
                        @if($tile->yearRange())
                            <span class="mt-0.5 block text-[11px] text-stone-400">{{ $tile->yearRange() }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        @if($hasMore)
            <div class="flex justify-center py-12">
                <button type="button" wire:click="loadMore"
                        class="rounded-full border border-white/20 px-7 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                    <span wire:loading.remove wire:target="loadMore">Arată mai multe · {{ number_format($total - $tiles->count(), 0, ',', '.') }} rămase</span>
                    <span wire:loading wire:target="loadMore">Se încarcă…</span>
                </button>
            </div>
        @else
            <div class="py-10"></div>
        @endif
    @endif

    {{-- Closing band, on the light ground the rest of the shop uses, so the dark wall ends on a
         deliberate note instead of trailing into the page's bottom padding. --}}
    <section class="bg-stone-50 pt-14">
        <div class="shell flex flex-col items-start justify-between gap-6 rounded-2xl bg-white p-8 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-lg font-bold">Nu găsești mașina ta?</h2>
                <p class="mt-1 text-sm text-stone-600">
                    Spune-ne ce conduci și ce cauți — verificăm dacă putem aduce piesa și îți facem colecția.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-3">
                <a href="{{ route('customer.garage') }}" class="rounded-lg border border-stone-300 px-5 py-2.5 text-sm font-semibold transition hover:border-stone-900">
                    Adaugă mașina în garaj
                </a>
                <a href="{{ route('storefront.contact') }}" class="rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
                    Scrie-ne
                </a>
            </div>
        </div>
    </section>
</div>
