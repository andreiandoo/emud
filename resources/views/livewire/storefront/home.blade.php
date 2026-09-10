<div>
    <x-seo title="Piese și accesorii 4x4, off-road și overlanding"
           description="Alege-ți mașina și vezi doar piesele și accesoriile care i se potrivesc. 4x4, off-road, mudding și overlanding." />

    {{-- ============================================================ Hero --}}
    {{-- The terrain canvas is the "film" behind the headline: generated in the browser, so it
         costs one script and no video file. The gradient underneath is what shows if WebGL is
         unavailable or the device asked to save data. --}}
    <section class="relative isolate flex min-h-[min(100svh,60rem)] flex-col justify-end overflow-hidden bg-g0 text-bone"
             style="background: radial-gradient(55% 40% at 70% 47%, rgba(242,106,27,.3), transparent 70%), linear-gradient(180deg, #09090b 0%, #111114 26%, #231d19 48%, #1b1917 66%, #0e0f11 100%)">
        <canvas data-st-terrain wire:ignore class="absolute inset-0 -z-20 h-full w-full" aria-hidden="true"></canvas>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(90deg,rgba(14,15,17,.8)_0%,rgba(14,15,17,.35)_42%,transparent_68%),linear-gradient(180deg,rgba(14,15,17,.6)_0%,transparent_24%,transparent_56%,rgba(14,15,17,.92)_100%)]"></div>

        <div class="shell grid gap-7 pb-12 pt-[calc(var(--st-header-h)+4.5rem)]">
            <p class="st-kicker text-[#c9cac6]" data-st-rise>4x4 · Mudding · Off-road · Overlanding</p>

            <h1 class="st-display max-w-[11.5ch] text-[clamp(2.9rem,7.4vw,7.5rem)] leading-[.9]" data-st-split wire:ignore>
                Pentru locurile unde se termină asfaltul
            </h1>

            <p class="max-w-[46ch] text-[clamp(1rem,1.25vw,1.2rem)] leading-relaxed text-[#cfcdc6]" data-st-rise>
                Piese, echipament și oameni cu care să le folosești. Spune-ne ce mașină ai și îți arătăm doar ce i se potrivește.
            </p>

            <div class="flex flex-wrap gap-3" data-st-rise>
                <a href="#top-sellers" class="st-btn">Intră în magazin <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
                <a href="{{ route('storefront.collections') }}" class="st-btn st-btn--ghost">Colecții pe model de mașină</a>
            </div>
        </div>

        <div class="relative border-t border-white/[.08] bg-g0/60 backdrop-blur-xl" data-st-rise>
            <div class="shell py-5">
                <livewire:storefront.vehicle-picker />

                @if($vehicle)
                    <p class="mt-4 text-sm text-mute">
                        Rezultatele sunt filtrate pentru <span class="font-semibold text-bone">{{ $vehicle->label() }}</span>.
                        @if($vehicle->isFromGarage())
                            Mașina vine din garajul tău.
                        @endif
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- ============================================================ Ticker --}}
    @php($promises = array_values(array_filter([
        'Compatibilitate verificată pe marcă, model și generație',
        'Caută după seria de șasiu (VIN)',
        $serviceCount > 0 ? $serviceCount.' '.($serviceCount === 1 ? 'service partener' : 'service-uri partenere').' pentru montaj' : null,
        isset($metrics['products']) ? number_format($metrics['products']['value'], 0, ',', '.').' de produse în magazin' : null,
        'Retur în 14 zile',
    ])))

    <div class="st-ticker bg-g0" data-st-ticker wire:ignore aria-hidden="true">
        <div class="st-ticker__track">
            @foreach([1, 2] as $copy)
                @foreach($promises as $promise)
                    <span>{{ $promise }}</span>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- ============================================================ Manifesto + numbers --}}
    <section class="bg-g0 py-[clamp(5.5rem,10vw,9.5rem)] text-bone">
        <div class="shell">
            <div class="grid gap-8 lg:grid-cols-[1fr_2.4fr] lg:gap-12">
                <p class="st-kicker text-mute">De ce eMUD</p>

                <p class="font-display text-[clamp(1.75rem,3.4vw,3.4rem)] font-medium leading-[1.14] tracking-[-.022em] [text-wrap:pretty]" data-st-words wire:ignore>
                    Aici găsești piesele care se potrivesc pe mașina ta, <span class="text-signal">service-ul care ți le montează</span> și oamenii cu care să le încerci pe traseu. Un magazin construit în jurul drumului, nu al raftului.
                </p>
            </div>

            @if($metrics->isNotEmpty())
                <dl class="mt-[clamp(3.5rem,7vw,6rem)] grid grid-cols-2 border-t border-gl lg:grid-cols-4" wire:ignore>
                    @foreach($metrics as $metric)
                        <div class="grid content-start gap-2 border-b border-gl py-7 pr-6 even:border-l even:pl-6 lg:border-b-0 lg:border-l lg:pl-6 lg:first:border-l-0 lg:first:pl-0">
                            <dd class="order-first font-display text-[clamp(2.2rem,3.6vw,3.5rem)] font-semibold leading-none tracking-[-.03em] tabular-nums"
                                data-st-count="{{ $metric['value'] }}">{{ number_format($metric['value'], 0, ',', '.') }}</dd>
                            <dt class="text-sm text-mute">{{ $metric['label'] }}</dt>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>

    {{-- ============================================================ Choose your ride --}}
    @if($collections->isNotEmpty())
        @php($scenes = ['dusk', 'steel', 'sand', 'mud', 'forest', 'snow'])

        <section id="masini" class="bg-g0 pb-[clamp(5.5rem,10vw,9.5rem)] text-bone">
            <div class="shell">
                <div class="mb-12 flex flex-wrap items-end justify-between gap-8">
                    <div class="grid max-w-3xl gap-5">
                        <p class="st-kicker text-mute">Choose your ride</p>
                        <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Fiecare mașină are catalogul ei</h2>
                        <p class="max-w-[52ch] text-mute">Alege marca și modelul. De acolo vezi doar ce se montează fără adaptări.</p>
                    </div>

                    <a href="{{ route('storefront.collections') }}" class="st-link text-bone">Toate colecțiile <x-storefront.icon name="arrow-right" /></a>
                </div>

                <div data-st-carousel data-st-cursor="Trage">
                    <ul class="st-rail" aria-label="Colecții pe model de mașină">
                        @foreach($collections as $index => $collection)
                            <li class="w-[min(78vw,23rem)]" wire:key="ride-{{ $collection->id }}">
                                <a href="{{ $collection->url() }}" draggable="false"
                                   class="st-tile group flex aspect-[3/4.1] flex-col justify-between bg-g2 p-5 text-bone">
                                    @if($collection->squareImageUrl())
                                        <img src="{{ $collection->squareImageUrl() }}" alt="" loading="lazy" draggable="false" class="st-media">
                                    @else
                                        <canvas class="st-media" data-st-scene="{{ $scenes[$index % count($scenes)] }}" data-seed="{{ $collection->id }}" aria-hidden="true"></canvas>
                                    @endif
                                    <span class="st-shade"></span>

                                    <span class="flex justify-between font-mono text-[11.5px] tracking-[.08em] text-bone/70">
                                        <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span>{{ $collection->yearRange() }}</span>
                                    </span>

                                    <span class="block">
                                        <span class="block font-display text-[clamp(2rem,3vw,2.9rem)] font-semibold leading-[.95] tracking-[-.03em]">{{ $collection->name }}</span>
                                        @if($collection->subtitle)
                                            <span class="mt-2 line-clamp-2 block text-sm text-bone/70">{{ $collection->subtitle }}</span>
                                        @endif
                                        <span class="st-underline mt-5 flex items-center justify-between border-t border-white/20 pt-3.5 text-[12px] font-semibold uppercase tracking-[.12em]">
                                            Vezi piesele <x-storefront.icon name="arrow-right" class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1" />
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach

                        <li class="w-[min(78vw,23rem)]">
                            <a href="{{ route('storefront.collections') }}" draggable="false"
                               class="group flex aspect-[3/4.1] flex-col justify-end gap-5 rounded-[3px] bg-sand p-6 text-ink">
                                <span class="st-kicker text-sandink">Toate mărcile</span>
                                <span class="st-display text-[clamp(1.9rem,2.6vw,2.5rem)]">Nu-ți găsești mașina?</span>
                                <span class="text-sm text-sandink">Caută-o după nume sau după seria de șasiu. Dacă nu o avem, scrie-ne și îi facem colecția.</span>
                                <span class="st-underline flex items-center justify-between border-t border-sandink/25 pt-3.5 text-[12px] font-semibold uppercase tracking-[.12em]">
                                    Caută mașina <x-storefront.icon name="arrow-right" class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1" />
                                </span>
                            </a>
                        </li>
                    </ul>

                    <div class="mt-8 flex items-center gap-6 text-bone">
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

    {{-- ============================================================ Top sellers + catalogue --}}
    <section id="top-sellers" class="scroll-mt-24 bg-light py-[clamp(5.5rem,10vw,9.5rem)] text-ink">
        <div class="shell">
            <div class="mb-12 flex flex-wrap items-end justify-between gap-8">
                <div class="grid max-w-3xl gap-5">
                    <p class="st-kicker text-ink2">Top sellers{{ $vehicle ? ' · pentru '.$vehicle->label() : '' }}</p>
                    <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Ce se montează cel mai des</h2>
                </div>

                <a href="{{ route('storefront.search') }}" class="st-link">Tot catalogul <x-storefront.icon name="arrow-right" /></a>
            </div>

            @if($bestSellers->isEmpty())
                <p class="rounded-[3px] border border-dashed border-line2 p-8 text-ink2">
                    {{ $vehicle ? 'Nu avem încă produse marcate compatibile cu '.$vehicle->label().'.' : 'Catalogul se completează în aceste zile.' }}
                    <a href="{{ route('storefront.contact') }}" class="font-semibold text-ink underline underline-offset-2">Scrie-ne ce cauți</a> și îl aducem.
                </p>
            @else
                <div data-st-carousel data-st-cursor="Trage">
                    <ul class="st-rail" aria-label="Cele mai vândute produse">
                        @foreach($bestSellers as $product)
                            <li class="w-[min(74vw,20rem)]" wire:key="best-{{ $product->id }}">
                                @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $verdicts($product)])
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
            @endif

            @if($categories->isNotEmpty())
                <div class="mt-[clamp(4.5rem,8vw,7rem)] mb-8 flex items-end justify-between gap-6">
                    <h3 class="st-display text-[clamp(1.75rem,2.6vw,2.4rem)]">Tot catalogul, pe categorii</h3>
                </div>

                <div class="grid gap-px overflow-hidden rounded-[3px] border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($categories as $category)
                        <a href="{{ route('storefront.category', $category['path']) }}" class="group grid grid-cols-[2.75rem_1fr_auto] items-center gap-4 bg-white p-5 transition hover:bg-light">
                            <x-storefront.icon :name="$category['icon']" class="h-7 w-7 text-ink" />
                            <span class="min-w-0">
                                <span class="block truncate font-display text-[17px] font-semibold leading-tight">{{ $category['name'] }}</span>
                                <span class="font-mono text-xs text-ink2">{{ $category['children']->count() }} {{ $category['children']->count() === 1 ? 'subcategorie' : 'subcategorii' }}</span>
                            </span>
                            <x-storefront.icon name="arrow-right" class="h-4 w-4 text-ink2 transition-transform duration-500 group-hover:translate-x-1 group-hover:text-ink" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================================ Shop by surface --}}
    <section class="bg-g0 py-[clamp(5.5rem,10vw,9.5rem)] text-bone" wire:ignore>
        <div class="shell">
            <div class="mb-12 flex flex-wrap items-end justify-between gap-8">
                <div class="grid max-w-3xl gap-5">
                    <p class="st-kicker text-mute">Shop by surface</p>
                    <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Unde mergi în weekend?</h2>
                </div>
                <p class="max-w-[40ch] text-sm text-mute">Presiunile din colț sunt valori de pornire pentru anvelopele de teren. Ajustează-le după greutatea mașinii și după anvelope.</p>
            </div>

            <div class="st-surface" data-st-surface x-data="{ open: 0, hover: window.matchMedia('(hover: hover)').matches }">
                @foreach($surfaces as $index => $surface)
                    <article @class(['st-surface__panel', 'is-open' => $index === 0]) :class="{ 'is-open': open === {{ $index }} }"
                             tabindex="0" aria-label="{{ $surface['name'] }}"
                             @click="open = {{ $index }}" @focusin="open = {{ $index }}" @mouseenter="if (hover) open = {{ $index }}">
                        <canvas class="absolute inset-0 -z-20 h-full w-full" data-st-scene="{{ $surface['scene'] }}" data-seed="{{ $surface['seed'] }}" data-st-fx="{{ $surface['key'] }}" aria-hidden="true"></canvas>

                        <div class="absolute inset-x-6 top-5 flex justify-between gap-3 font-mono text-[11.5px] uppercase tracking-[.08em] text-bone/75">
                            <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) count($surfaces), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="st-surface__meta max-lg:hidden">Presiune de pornire {{ $surface['pressure'] }}</span>
                        </div>

                        <span class="st-surface__label" aria-hidden="true">{{ $surface['name'] }}</span>

                        <div class="st-surface__body">
                            <h3 class="st-display text-[clamp(3rem,5vw,5.25rem)] leading-[.9] tracking-[-.04em]">{{ $surface['name'] }}</h3>
                            <p class="max-w-[44ch] text-bone/80">{{ $surface['text'] }}</p>

                            @if($surface['links']->isNotEmpty())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($surface['links'] as $link)
                                        <a href="{{ route('storefront.category', $link->full_path) }}" class="st-chip text-bone hover:bg-bone hover:text-ink">{{ $link->name }}</a>
                                    @endforeach
                                </div>

                                <div>
                                    <a href="{{ route('storefront.category', $surface['links']->first()->full_path) }}" class="st-btn">
                                        Echipează-te pentru {{ mb_strtolower($surface['name']) }} <x-storefront.icon name="arrow-right" class="st-arrow" />
                                    </a>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================================================ The trail --}}
    {{-- Scroll drives a camera along a route across the terrain. The stops are the four places
         any day out has, and what helps at each; the copy is here, the scene reads it. --}}
    <section data-st-trail class="relative bg-[#121214] text-bone" wire:ignore aria-labelledby="trail-title">
        <div class="st-trail__stage" data-st-trail-stage>
            <canvas data-st-trail-gl class="absolute inset-0 -z-20 h-full w-full" aria-hidden="true"></canvas>

            <div class="pointer-events-none absolute inset-0 z-[1]" aria-hidden="true">
                @foreach($stops as $stop)
                    <div class="st-trail__label" data-st-trail-label><span>{{ $stop['title'] }}</span></div>
                @endforeach
            </div>

            <div class="absolute inset-0 z-[2] flex items-center max-lg:items-end max-lg:pb-7">
                <div class="shell">
                    <div class="grid w-[min(28rem,100%)] gap-5 pt-20 max-lg:pt-0">
                        <p class="st-kicker text-mute max-lg:hidden">Comunitatea eMUD</p>
                        <h2 id="trail-title" class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Ce iei cu tine pe traseu</h2>
                        <p class="text-mute max-lg:hidden">Patru opriri pe care le are orice tură de o zi, și ce te ajută la fiecare. Derulează ca să le parcurgi.</p>

                        <ol class="border-t border-gl">
                            @foreach($stops as $index => $stop)
                                <li @class(['st-trail__stop grid grid-cols-[3.25rem_1fr] gap-x-3.5 gap-y-1 border-b border-gl py-3', 'is-on' => $index === 0])
                                    data-st-trail-stop data-t="{{ $stop['t'] }}">
                                    <b class="font-mono text-xs font-medium leading-6">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</b>
                                    <strong class="font-semibold">{{ $stop['title'] }}</strong>
                                    <p class="col-start-2 text-[13.5px] text-mute">
                                        {{ $stop['text'] }}
                                        @foreach($stop['links'] as $link)
                                            <a href="{{ route('storefront.category', $link->full_path) }}" class="whitespace-nowrap text-bone underline underline-offset-2 hover:text-signal">{{ $link->name }}</a>@if(! $loop->last), @endif
                                        @endforeach
                                    </p>
                                </li>
                            @endforeach
                        </ol>

                        <div class="flex flex-wrap gap-2.5">
                            <a href="{{ route('storefront.guides') }}" class="st-btn">Ghiduri de traseu <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
                            <a href="#newsletter" class="st-btn st-btn--ghost">Anunță-mă de ture</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-9 right-[var(--st-pad)] z-[2] grid justify-items-end gap-3 max-lg:top-28 max-lg:bottom-auto">
                <p class="font-display text-[clamp(2.5rem,4vw,4rem)] font-semibold leading-none tracking-[-.03em]">
                    <span data-st-trail-count>01</span><small class="font-mono text-sm font-normal tracking-normal text-mute"> / {{ str_pad((string) count($stops), 2, '0', STR_PAD_LEFT) }}</small>
                </p>
                <svg data-st-trail-map class="h-[140px] w-[180px] rounded-[3px] border border-gl2 bg-g0/70 backdrop-blur max-lg:hidden" viewBox="0 0 180 140" aria-hidden="true"></svg>
            </div>

            <p data-st-trail-hint class="absolute bottom-7 left-1/2 z-[2] -translate-x-1/2 font-mono text-[10.5px] uppercase tracking-[.12em] text-mute transition-opacity duration-500 max-lg:hidden">
                Derulează ca să parcurgi traseul
            </p>
        </div>
    </section>

    {{-- ============================================================ News --}}
    @if($articles->isNotEmpty())
        @php($articleScenes = ['mud', 'sand', 'forest'])

        <section class="bg-light py-[clamp(5.5rem,10vw,9.5rem)] text-ink">
            <div class="shell">
                <div class="mb-12 flex flex-wrap items-end justify-between gap-8">
                    <div class="grid max-w-3xl gap-5">
                        <p class="st-kicker text-ink2">News & events</p>
                        <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Din atelier și de pe teren</h2>
                    </div>

                    <a href="{{ route('storefront.guides') }}" class="st-link">Toate ghidurile <x-storefront.icon name="arrow-right" /></a>
                </div>

                <div class="grid gap-x-6 gap-y-12 md:grid-cols-3">
                    @foreach($articles as $index => $article)
                        <a href="{{ route('storefront.guide', $article->slug) }}" class="group grid content-start gap-4">
                            <div class="st-tile aspect-[16/11] bg-g2">
                                <canvas class="st-media" data-st-scene="{{ $articleScenes[$index % 3] }}" data-seed="{{ $article->id * 7 }}" aria-hidden="true"></canvas>
                            </div>

                            <div class="flex gap-3.5 font-mono text-[12px] uppercase tracking-[.06em] text-ink2">
                                <span>{{ $article->category?->name ?? 'Ghid' }}</span>
                                <span>{{ $article->published_at?->format('d.m.Y') }}</span>
                            </div>

                            <h3 class="font-display text-[1.4rem] font-semibold leading-tight tracking-[-.015em] text-balance transition group-hover:text-signal">{{ $article->title }}</h3>

                            @if($article->excerpt)
                                <p class="line-clamp-3 text-[15px] text-ink2">{{ $article->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============================================================ Reviews --}}
    @if($reviews->isNotEmpty())
        <section class="bg-g0 py-[clamp(5.5rem,10vw,9.5rem)] text-bone">
            <div class="shell">
                <div class="mb-12 grid max-w-3xl gap-5">
                    <p class="st-kicker text-mute">Recenzii</p>
                    <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Din garajele clienților</h2>
                </div>

                <div data-st-carousel data-st-cursor="Trage">
                    <ul class="st-rail" aria-label="Recenzii">
                        @foreach($reviews as $review)
                            @php($vehicleLabel = $review->vehicleLabel())
                            <li class="w-[min(84vw,26rem)]" wire:key="review-{{ $review->id }}">
                                <figure class="flex h-full flex-col gap-5 rounded-[3px] border border-gl bg-g2 p-7">
                                    @if($review->rating)
                                        <p class="flex gap-0.5 text-signal" aria-label="{{ $review->rating }} din 5">
                                            @for($star = 1; $star <= 5; $star++)
                                                <x-storefront.icon name="star" class="h-4 w-4 {{ $star <= $review->rating ? 'fill-current' : 'opacity-30' }}" />
                                            @endfor
                                        </p>
                                    @endif

                                    @if($review->title)
                                        <p class="font-display text-xl font-semibold leading-snug">{{ $review->title }}</p>
                                    @endif

                                    <blockquote class="flex-1 text-[15.5px] leading-relaxed text-[#d8d6cf]">{{ $review->body }}</blockquote>

                                    <figcaption class="flex items-center gap-3 border-t border-gl pt-4 text-sm">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gl2 font-semibold">{{ mb_substr($review->reviewer_name, 0, 1) }}</span>
                                        <span class="min-w-0">
                                            <span class="block font-semibold">{{ $review->reviewer_name }}</span>
                                            <span class="block truncate text-mute">{{ collect([$vehicleLabel, $review->reviewer_location])->filter()->implode(' · ') }}</span>
                                        </span>
                                    </figcaption>
                                </figure>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8 flex items-center gap-6">
                        <span class="min-w-16 font-mono text-[13px] text-mute" data-st-counter></span>
                        <div class="st-progress"><i></i></div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ============================================================ Fitting --}}
    <section class="bg-sand py-[clamp(5.5rem,10vw,9.5rem)] text-ink">
        <div class="shell grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-24">
            <div class="grid content-start gap-6">
                <p class="st-kicker text-sandink">Montaj</p>
                <h2 class="st-display text-[clamp(2.25rem,4.6vw,4.75rem)]">Comanzi online. Montezi la un service partener.</h2>
                <p class="max-w-[52ch] text-sandink">
                    După comandă îți arătăm service-urile din orașul tău care montează piesele cumpărate de la noi. Ceri programarea de acolo, iar service-ul te contactează să confirme ora.
                </p>
                <div><a href="{{ route('storefront.services') }}" class="st-btn st-btn--ink">Găsește un service <x-storefront.icon name="arrow-right" class="st-arrow" /></a></div>
            </div>

            <div>
                <ol class="border-t border-sandink/40">
                    @foreach([
                        ['Comanzi piesa', 'din magazin, pentru mașina ta.'],
                        ['Alegi service-ul', 'dintre partenerii din orașul tău, chiar de pe pagina comenzii.'],
                        ['Ceri programarea', 'iar service-ul te sună să stabiliți ora.'],
                    ] as $index => [$title, $text])
                        <li class="grid grid-cols-[4rem_1fr] gap-4 border-b border-sandink/20 py-6">
                            <b class="font-display text-3xl font-semibold leading-none text-signal">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</b>
                            <p><strong class="block font-display text-lg font-semibold">{{ $title }}</strong><span class="text-sandink">{{ $text }}</span></p>
                        </li>
                    @endforeach
                </ol>

                @if($cities->isNotEmpty())
                    <div class="mt-7 grid gap-2.5 sm:grid-cols-2">
                        @foreach($cities as $city)
                            <a href="{{ route('storefront.services.city', $city->city_slug) }}"
                               class="flex items-center justify-between gap-3 rounded-[3px] bg-[#eee7d9] px-4 py-3.5 transition hover:bg-ink hover:text-light">
                                <b class="font-semibold">{{ $city->city }}</b>
                                <span class="font-mono text-xs opacity-75">{{ $city->shops }} {{ (int) $city->shops === 1 ? 'service' : 'service-uri' }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
