<div>
    {{-- Search result pages are per-query and endless; indexing them buys nothing and dilutes
         the pages that should rank. --}}
    <x-seo title="Căutare" :index="false" :follow="true" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-14 sm:pt-20">
            <h1 class="st-kicker text-mute">Căutare</h1>

            <label class="mt-6 flex max-w-4xl items-center gap-4 border-b border-gl2 pb-3 focus-within:border-bone">
                <span class="sr-only">Caută în catalog</span>
                <x-storefront.icon name="search" class="h-8 w-8 shrink-0 text-mute" />
                <input type="search" wire:model.live.debounce.400ms="query" placeholder="Cod piesă, MPN sau denumire" autofocus
                       class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 font-display text-[clamp(1.75rem,4vw,3.5rem)] font-medium tracking-tight text-bone placeholder:text-mute2 focus:border-0 focus:shadow-none focus:ring-0">
                <span wire:loading wire:target="query" class="h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-gl2 border-t-bone"></span>
            </label>

            <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-mute">
                @if($vehicle)
                    <label class="flex items-center gap-2.5 text-[#d8d6cf]">
                        <input type="checkbox" wire:model.live="onlyForMyVehicle">
                        Doar pentru <span class="font-semibold text-bone">{{ $vehicle->label() }}</span>
                    </label>
                @endif

                <span>
                    Sau găsește mașina după
                    <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'vin' })" class="font-semibold text-bone underline underline-offset-2">serie de șasiu (VIN)</button>
                </span>
            </div>
        </div>
    </section>

    <div class="shell pb-24 pt-10">
        @if(trim($query) === '')
            <p class="text-ink2">Scrie un cod de piesă sau o denumire ca să cauți.</p>
        @elseif($products->isEmpty())
            <div class="grid place-items-center gap-3 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                <p class="font-display text-xl font-semibold">Niciun rezultat pentru „{{ $query }}”.</p>
                @if($vehicle && $onlyForMyVehicle)
                    <p class="text-sm text-ink2">Încearcă fără filtrul de compatibilitate.</p>
                @endif
            </div>
        @else
            <p class="mb-6 text-ink2"><span class="font-display text-2xl font-semibold text-ink">{{ $products->total() }}</span> rezultate</p>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($products as $product)
                    @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $vehicle ? $verdicts($product) : null])
                @endforeach
            </div>

            <div class="mt-10">{{ $products->links() }}</div>
        @endif
    </div>
</div>
