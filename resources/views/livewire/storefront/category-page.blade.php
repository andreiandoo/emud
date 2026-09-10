<div>
    {{-- Filtered and paginated views are the same catalogue sliced differently, so the canonical
         always points at the unfiltered first page. --}}
    <x-seo :title="$category->name"
           :description="$category->description ?? 'Piese și accesorii 4x4 din categoria '.$category->name"
           :canonical="route('storefront.category', $category)" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        @if($category->image_path)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt=""
                 class="absolute inset-0 -z-20 h-full w-full object-cover opacity-45">
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-g0 via-g0/80 to-g0/30"></div>
        @else
            <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        @endif

        <div class="shell pb-12 pt-14 sm:pt-20">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.home') }}" class="transition hover:text-bone">Acasă</a>
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $category->name }}</span>
            </nav>

            <h1 class="st-display mt-6 max-w-4xl text-[clamp(2.4rem,5.6vw,5.25rem)] leading-[.92]">{{ $category->name }}</h1>
            @if($category->description)
                <p class="mt-5 max-w-2xl text-[#cfcdc6]">{{ $category->description }}</p>
            @endif

            @if($children->isNotEmpty())
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach($children as $child)
                        <a href="{{ route('storefront.category', $child) }}" class="st-chip text-bone hover:bg-bone hover:text-ink">
                            {{ $child->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="shell pb-24">
        <div class="sticky top-0 z-30 -mx-[var(--st-pad)] mb-8 flex flex-wrap items-center gap-x-5 gap-y-3 border-b border-line bg-light/95 px-[var(--st-pad)] py-4 backdrop-blur">
            @if($vehicle)
                <label class="flex items-center gap-2.5 text-sm">
                    <input type="checkbox" wire:model.live="onlyForMyVehicle">
                    Doar pentru <span class="font-semibold">{{ $vehicle->label() }}</span>
                </label>
            @else
                <span class="text-sm text-ink2">
                    <button type="button" @click="$dispatch('open-vehicle-selector', { tab: 'car' })" class="font-semibold text-ink underline underline-offset-2">Alege-ți mașina</button>
                    ca să vezi doar piesele compatibile.
                </span>
            @endif

            <p class="text-sm text-ink2 max-lg:hidden">
                <span class="font-display text-lg font-semibold text-ink">{{ $products->total() }}</span> {{ $products->total() === 1 ? 'produs' : 'produse' }}
            </p>

            <div class="ml-auto flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm">
                    <span class="field-label mb-0">Marcă</span>
                    <select wire:model.live="brand" class="min-h-10 w-auto py-2 text-sm">
                        <option value="">Toate</option>
                        @foreach($brands as $availableBrand)
                            <option value="{{ $availableBrand->slug }}">{{ $availableBrand->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <span class="field-label mb-0">Sortare</span>
                    <select wire:model.live="sort" class="min-h-10 w-auto py-2 text-sm">
                        <option value="relevance">Recomandate</option>
                        <option value="name">Denumire</option>
                        <option value="newest">Cele mai noi</option>
                    </select>
                </label>
            </div>
        </div>

        @if($products->isEmpty())
            <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
                <x-storefront.icon name="part" class="h-10 w-10 text-line2" />
                <p class="max-w-lg font-display text-xl font-semibold">
                    @if($vehicle && $onlyForMyVehicle)
                        Niciun produs din această categorie nu este marcat compatibil cu {{ $vehicle->label() }}.
                    @else
                        Nu există încă produse publicate în această categorie.
                    @endif
                </p>
                @if($vehicle && $onlyForMyVehicle)
                    <p class="text-sm text-ink2">Debifează filtrul ca să vezi toată categoria.</p>
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
</div>
