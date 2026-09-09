<div class="space-y-8">
    <x-seo title="Service auto în România"
           description="Ateliere și service-uri auto din România, filtrate după oraș, lucrare și specializare. Program, prețuri orientative și cerere de programare." />

    <header class="space-y-2">
        <h1 class="text-3xl font-bold tracking-tight">Service auto în România</h1>
        <p class="text-sm text-stone-600">
            Ateliere pe orașe și lucrări. Cere o programare direct, sau sună service-ul.
        </p>
    </header>

    <div class="space-y-4 rounded-xl border border-stone-200 bg-white p-5">
        <label class="relative block">
            <span class="sr-only">Caută un service</span>
            <x-storefront.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
            <input wire:model.live.debounce.400ms="search" class="h-11 pl-9" placeholder="Nume service, oraș sau stradă">
        </label>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Județ</span>
                <select wire:model.live="county">
                    <option value="">Toate</option>
                    @foreach($counties as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Oraș</span>
                <select wire:model.live="city" @disabled($cities->isEmpty())>
                    <option value="">{{ $cities->isEmpty() ? 'Alege întâi județul' : 'Toate' }}</option>
                    @foreach($cities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Lucrare</span>
                <select wire:model.live="service">
                    <option value="">Orice lucrare</option>
                    @foreach($serviceOptions as $option)<option value="{{ $option->slug }}">{{ $option->name }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Specializare</span>
                <select wire:model.live="speciality">
                    <option value="">Toate</option>
                    @foreach($specialities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-5 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model.live="fitsOurParts">
                Montează piese cumpărate de la noi
            </label>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model.live="openNow">
                Deschis acum
            </label>

            @if($search !== '' || $county !== '' || $city !== '' || $speciality !== '' || $service !== '' || $fitsOurParts || $openNow)
                <button type="button" wire:click="resetFilters" class="ml-auto text-sm font-semibold text-stone-500 underline hover:text-stone-900">
                    Golește filtrele
                </button>
            @endif
        </div>
    </div>

    {{-- Said next to the list, not only on the card: the order itself is what money bought, so
         the reader has to be told before they read it as a ranking of quality. --}}
    <p class="text-xs text-stone-500">
        Ordinea implicită este influențată de listările plătite, care sunt marcate ca atare.
        În rest, sortăm alfabetic.
    </p>

    @if($shops->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Niciun service nu corespunde filtrelor alese.
        </p>
    @else
        <div class="space-y-3">
            @foreach($shops as $shop)
                <x-storefront.shop-card :shop="$shop" />
            @endforeach
        </div>

        <div>{{ $shops->links() }}</div>
    @endif

    @if($topCities->isNotEmpty())
        <section class="space-y-3 border-t border-stone-200 pt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-stone-500">Orașe cu cele mai multe service-uri</h2>

            <div class="flex flex-wrap gap-2">
                @foreach($topCities as $row)
                    <a href="{{ route('storefront.services.city', $row->city_slug) }}"
                       class="rounded-full border border-stone-300 px-3 py-1.5 text-sm text-stone-700 transition hover:border-stone-900 hover:text-stone-900">
                        {{ $row->city }} <span class="text-stone-400">{{ $row->total }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
