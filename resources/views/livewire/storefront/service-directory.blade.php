<div>
    <x-seo title="Service auto în România"
           description="Ateliere și service-uri auto din România, filtrate după oraș, lucrare și specializare. Program, prețuri orientative și cerere de programare." />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(45%_60%_at_80%_10%,rgba(242,106,27,.13),transparent_70%)]"></div>

        <div class="shell pb-10 pt-16 sm:pt-24">
            <p class="st-kicker text-mute">Service auto</p>
            <h1 class="st-display mt-5 max-w-[16ch] text-[clamp(2.6rem,6vw,5.75rem)] leading-[.92]">Service auto în România</h1>
            <p class="mt-5 max-w-[56ch] text-[clamp(1rem,1.2vw,1.15rem)] text-[#cfcdc6]">
                Ateliere pe orașe și lucrări. Cere o programare direct, sau sună service-ul.
            </p>

            {{-- The search sits in the band, as the one thing to do on arrival. --}}
            <div class="mt-12 grid gap-4 rounded-[3px] border border-white/10 bg-white/[.03] p-5 backdrop-blur sm:p-6">
                <label class="flex items-center gap-3 border-b border-gl2 pb-3 focus-within:border-bone">
                    <span class="sr-only">Caută un service</span>
                    <x-storefront.icon name="search" class="h-5 w-5 shrink-0 text-mute" />
                    <input wire:model.live.debounce.400ms="search" placeholder="Nume service, oraș sau stradă"
                           class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 text-lg text-bone placeholder:text-mute2 focus:border-0 focus:ring-0">
                </label>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach([
                        ['county', 'Județ', $counties, 'Toate', false],
                        ['city', 'Oraș', $cities, $cities->isEmpty() ? 'Alege întâi județul' : 'Toate', $cities->isEmpty()],
                        ['service', 'Lucrare', $serviceOptions, 'Orice lucrare', false],
                        ['speciality', 'Specializare', $specialities, 'Toate', false],
                    ] as [$field, $label, $options, $placeholder, $disabled])
                        <label class="block">
                            <span class="mb-1.5 block font-mono text-[10.5px] uppercase tracking-[.1em] text-mute">{{ $label }}</span>
                            <select wire:model.live="{{ $field }}" @disabled($disabled)
                                    class="border-gl2 bg-g1 text-bone focus:border-bone focus:ring-bone disabled:bg-g2 disabled:text-mute2 [&_option]:bg-g1">
                                <option value="">{{ $placeholder }}</option>
                                @foreach($options as $option)
                                    @if($field === 'service')
                                        <option value="{{ $option->slug }}">{{ $option->name }}</option>
                                    @else
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-[#d8d6cf]">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" wire:model.live="fitsOurParts">
                        Montează piese cumpărate de la noi
                    </label>

                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" wire:model.live="openNow">
                        Deschis acum
                    </label>

                    @if($search !== '' || $county !== '' || $city !== '' || $speciality !== '' || $service !== '' || $fitsOurParts || $openNow)
                        <button type="button" wire:click="resetFilters" class="ml-auto text-sm font-semibold text-mute underline underline-offset-2 hover:text-bone">
                            Golește filtrele
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-14">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <p class="font-display text-2xl font-semibold">
                {{ $shops->total() }} {{ $shops->total() === 1 ? 'service' : 'service-uri' }}
            </p>

            {{-- Said next to the list, not only on the card: the order itself is what money bought,
                 so the reader has to be told before they read it as a ranking of quality. --}}
            <p class="max-w-[52ch] text-xs text-ink2">
                Ordinea implicită este influențată de listările plătite, care sunt marcate ca atare.
                În rest, sortăm alfabetic.
            </p>
        </div>

        @if($shops->isEmpty())
            <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                <x-storefront.icon name="wrench" class="h-10 w-10 text-line2" />
                <p class="font-display text-xl font-semibold">Niciun service nu corespunde filtrelor alese.</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($shops as $shop)
                    <x-storefront.shop-card :shop="$shop" />
                @endforeach
            </div>

            <div class="mt-8">{{ $shops->links() }}</div>
        @endif

        @if($topCities->isNotEmpty())
            <section class="mt-16 border-t border-line pt-10">
                <h2 class="st-kicker mb-5 text-ink2">Orașe cu cele mai multe service-uri</h2>

                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($topCities as $row)
                        <a href="{{ route('storefront.services.city', $row->city_slug) }}"
                           class="group flex items-center justify-between gap-3 rounded-[3px] bg-white px-4 py-3.5 transition hover:bg-ink hover:text-light">
                            <span class="font-semibold">{{ $row->city }}</span>
                            <span class="font-mono text-xs text-ink2 group-hover:text-light/70">{{ $row->total }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
