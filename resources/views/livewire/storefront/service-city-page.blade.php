<div>
    <x-seo :title="'Service auto în '.$cityName"
           :description="'Ateliere și service-uri auto din '.$cityName.'. Program, lucrări, prețuri orientative și cerere de programare online.'"
           :canonical="route('storefront.services.city', $city)" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-10 pt-14 sm:pt-20">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('storefront.services') }}" class="transition hover:text-bone">Service auto</a>
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $cityName }}</span>
            </nav>

            <h1 class="st-display mt-6 text-[clamp(2.4rem,5.4vw,5rem)] leading-[.92]">Service auto în {{ $cityName }}</h1>
            <p class="mt-4 text-[#cfcdc6]">{{ $shops->total() }} ateliere listate. Ordinea este influențată de listările plătite, marcate ca atare.</p>

            <div class="mt-10 flex flex-wrap items-end gap-x-6 gap-y-4 rounded-[3px] border border-white/10 bg-white/[.03] p-5 text-sm text-[#d8d6cf]">
                <label class="block min-w-56">
                    <span class="mb-1.5 block font-mono text-[10.5px] uppercase tracking-[.1em] text-mute">Specializare</span>
                    <select wire:model.live="speciality" class="border-gl2 bg-g1 text-bone focus:border-bone focus:ring-bone [&_option]:bg-g1">
                        <option value="">Toate</option>
                        @foreach($specialities as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2.5 pb-3">
                    <input type="checkbox" wire:model.live="fitsOurParts">
                    Montează piesele noastre
                </label>

                <label class="flex items-center gap-2.5 pb-3">
                    <input type="checkbox" wire:model.live="openNow">
                    Deschis acum
                </label>
            </div>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-14">
        @if($shops->isEmpty())
            <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                <x-storefront.icon name="wrench" class="h-10 w-10 text-line2" />
                <p class="font-display text-xl font-semibold">Niciun service din {{ $cityName }} nu corespunde filtrelor alese.</p>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach($shops as $shop)
                    <x-storefront.shop-card :shop="$shop" />
                @endforeach
            </div>

            <div class="mt-8">{{ $shops->links() }}</div>
        @endif

        <p class="mt-14 border-t border-line pt-6">
            <a href="{{ route('storefront.services') }}" class="st-link"><x-storefront.icon name="arrow-left" /> Vezi toate orașele</a>
        </p>
    </div>
</div>
