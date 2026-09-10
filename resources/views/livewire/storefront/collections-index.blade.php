{{-- Literal class strings, not built from a variable: Tailwind scans this file for the names it
     must generate, and a class assembled at render time is never in the stylesheet. --}}
@php($shades = [
    'bg-[linear-gradient(160deg,#2b2622_0%,#141518_70%)]',
    'bg-[linear-gradient(160deg,#35383e_0%,#141518_70%)]',
    'bg-[linear-gradient(160deg,#3a3127_0%,#101114_70%)]',
])

<div class="bg-g0 text-bone">
    <x-seo title="Colecții pe model de mașină"
           description="Caută-ți mașina și vezi tot ce ținem pentru ea: piese, accesorii off-road și echipare."
           :canonical="route('storefront.collections')" />

    {{-- The search box is the page. A visitor arrives knowing exactly what they drive, so the
         one thing worth putting in front of them is a box to type it into — everything else here
         is there to make that box feel like it is backed by something. --}}
    <section class="relative isolate overflow-hidden border-b border-gl">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(50%_60%_at_70%_20%,rgba(242,106,27,.14),transparent_70%)]"></div>

        <div class="shell pb-14 pt-16 sm:pb-20 sm:pt-24">
            <p class="st-kicker text-mute">Colecții pe model de mașină</p>
            <h1 class="st-display mt-5 text-[clamp(2.75rem,6.4vw,6.25rem)] leading-[.92]">Colecții</h1>
            <p class="mt-5 max-w-[52ch] text-[clamp(1rem,1.2vw,1.15rem)] text-[#cfcdc6]">
                Fiecare mașină are catalogul ei. Scrie marca sau modelul și vezi tot ce ținem pentru ea.
            </p>

            <div class="mt-10 max-w-3xl">
                <label class="flex items-center gap-4 border-b border-gl2 pb-3 focus-within:border-bone">
                    <span class="sr-only">Caută marca sau modelul mașinii</span>
                    <x-storefront.icon name="search" class="h-7 w-7 shrink-0 text-mute" />

                    <input type="search" wire:model.live.debounce.300ms="search"
                           placeholder="Dacia, Jimny, Hilux…" autocomplete="off"
                           class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 font-display text-[clamp(1.5rem,3vw,2.5rem)] font-medium tracking-tight text-bone placeholder:text-mute2 focus:border-0 focus:ring-0">

                    <span wire:loading wire:target="search" class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-gl2 border-t-bone"></span>
                    @if($search !== '')
                        <button type="button" wire:click="clearSearch" wire:loading.remove wire:target="search"
                                class="shrink-0 text-mute transition hover:text-bone" aria-label="Șterge căutarea">
                            <x-storefront.icon name="close" class="h-6 w-6" />
                        </button>
                    @endif
                </label>

                <p class="mt-4 text-sm text-mute" aria-live="polite">
                    @if($search !== '')
                        <span class="font-semibold text-bone">{{ $total }}</span>
                        {{ $total === 1 ? 'colecție găsită' : 'colecții găsite' }} pentru „{{ $search }}”
                    @else
                        Sau alege marca de mai jos.
                    @endif
                </p>
            </div>

            {{-- The numbers are here to answer "do they actually have anything for my car" before
                 the visitor has scrolled far enough to find out for themselves. A metric that
                 counts zero is dropped upstream, so the row is a flex, not a fixed grid. --}}
            @if($metrics !== [])
                <dl class="mt-14 flex flex-wrap gap-y-8 border-t border-gl pt-8">
                    @foreach($metrics as $metric)
                        {{-- flex-col-reverse rather than reordering the markup: a <dl> wants its
                             <dt> before its <dd>, and the number belongs on top. --}}
                        <div class="flex w-1/2 flex-col-reverse pr-6 sm:w-auto sm:border-l sm:border-gl sm:px-8 sm:first:border-l-0 sm:first:pl-0">
                            <dt class="mt-2 text-sm text-mute">{{ $metric['label'] }}</dt>
                            <dd class="font-display text-[clamp(1.9rem,3vw,2.75rem)] font-semibold leading-none tracking-[-.03em] tabular-nums">
                                {{ number_format($metric['value'], 0, ',', '.') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>

    {{-- The wall. Hairline seams rather than gutters: the tiles read as one surface. --}}
    @if($tiles->isEmpty())
        {{-- Two different silences. "Nothing matched" is a dead end the visitor can back out of;
             "there is nothing here yet" is the shop's problem, and offering to clear a search
             that was never typed would just be confusing. --}}
        <section class="shell py-24 text-center">
            @if($search !== '')
                <p class="font-display text-2xl font-semibold">Nicio colecție pentru „{{ $search }}”.</p>
                <p class="mt-2 text-mute">Încearcă doar marca, fără an sau motorizare.</p>
                <button type="button" wire:click="clearSearch" class="st-btn st-btn--ghost mt-8">
                    Vezi toate colecțiile
                </button>
            @else
                <p class="font-display text-2xl font-semibold">Încă nu am publicat nicio colecție.</p>
                <p class="mt-2 text-mute">Spune-ne ce mașină ai și îți facem una.</p>
                <a href="{{ route('storefront.contact') }}" class="st-btn st-btn--ghost mt-8">Scrie-ne</a>
            @endif
        </section>
    @else
        <div class="grid grid-cols-2 gap-px bg-gl sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
            @foreach($tiles as $tile)
                <a href="{{ $tile->url() }}" wire:key="tile-{{ $tile->id }}"
                   class="group relative isolate aspect-square overflow-hidden {{ $tile->squareImageUrl() ? 'bg-g2' : $shades[$loop->index % 3] }}">

                    @if($tile->squareImageUrl())
                        <img src="{{ $tile->squareImageUrl() }}" alt="{{ $tile->name }}"
                             loading="lazy" width="480" height="480"
                             class="absolute inset-0 -z-10 h-full w-full object-cover opacity-85 transition duration-[1200ms] ease-[cubic-bezier(.2,.8,.2,1)] group-hover:scale-[1.06] group-hover:opacity-100">
                    @endif

                    {{-- Two overlays: one keeps the name legible over any photograph, one lights the
                         tile on hover rather than merely moving it. Both sit at -z-10: above the
                         tile's ground, below the name, because the tile is an isolated context. --}}
                    <span class="absolute inset-0 -z-10 bg-linear-to-t from-g0/90 via-g0/25 to-transparent"></span>
                    <span class="absolute inset-0 -z-10 transition duration-500 group-hover:bg-white/[.04]"></span>

                    <span class="absolute inset-x-0 bottom-0 p-4 sm:p-5">
                        <span class="block font-display text-[clamp(1.05rem,1.5vw,1.4rem)] font-semibold leading-tight tracking-[-.01em] text-bone">
                            {{ $tile->name }}
                        </span>
                        @if($tile->children_count > 0)
                            <span class="mt-1 block font-mono text-[11px] uppercase tracking-[.08em] text-mute">
                                {{ $tile->children_count }} {{ $tile->children_count === 1 ? 'variantă' : 'variante' }}
                            </span>
                        @elseif($tile->yearRange())
                            <span class="mt-1 block font-mono text-[11px] uppercase tracking-[.08em] text-mute">{{ $tile->yearRange() }}</span>
                        @endif
                        <span class="mt-3 block h-px w-full origin-left scale-x-0 bg-signal transition-transform duration-700 ease-[cubic-bezier(.2,.8,.2,1)] group-hover:scale-x-100"></span>
                    </span>
                </a>
            @endforeach
        </div>

        @if($hasMore)
            <div class="flex justify-center py-14">
                <button type="button" wire:click="loadMore" class="st-btn st-btn--ghost">
                    <span wire:loading.remove wire:target="loadMore">Arată mai multe · {{ number_format($total - $tiles->count(), 0, ',', '.') }} rămase</span>
                    <span wire:loading wire:target="loadMore">Se încarcă…</span>
                </button>
            </div>
        @else
            <div class="py-10"></div>
        @endif
    @endif

    {{-- Closing band, on sand, so the dark wall ends on a deliberate note. --}}
    <section class="bg-sand text-ink">
        <div class="shell grid gap-8 py-16 sm:py-20 lg:grid-cols-[1.4fr_1fr] lg:items-end">
            <div class="grid gap-4">
                <p class="st-kicker text-sandink">Lipsește mașina ta?</p>
                <h2 class="st-display text-[clamp(2rem,3.6vw,3.5rem)]">Nu găsești mașina ta?</h2>
                <p class="max-w-[52ch] text-sandink">
                    Spune-ne ce conduci și ce cauți — verificăm dacă putem aduce piesa și îți facem colecția.
                </p>
            </div>
            <div class="flex flex-wrap gap-3 lg:justify-end">
                <a href="{{ route('customer.garage') }}" class="st-btn st-btn--outline">Adaugă mașina în garaj</a>
                <a href="{{ route('storefront.contact') }}" class="st-btn st-btn--ink">Scrie-ne <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
            </div>
        </div>
    </section>
</div>
