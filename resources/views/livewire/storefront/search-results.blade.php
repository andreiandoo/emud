<div class="space-y-6">
    {{-- Search result pages are per-query and endless; indexing them buys nothing and dilutes
         the pages that should rank. --}}
    <x-seo title="Căutare" :index="false" :follow="true" />

    <div>
        <h1 class="text-2xl font-black tracking-tight">Căutare</h1>
        <input type="search" wire:model.live.debounce.400ms="query" placeholder="Cod piesă, MPN sau denumire"
               class="mt-3 w-full max-w-xl rounded-lg border-stone-300 text-sm">
    </div>

    @if($vehicle)
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="onlyForMyVehicle" class="rounded border-stone-300">
            Doar pentru <span class="font-semibold">{{ $vehicle->label() }}</span>
        </label>
    @endif

    @if(trim($query) === '')
        <p class="text-sm text-stone-500">Scrie un cod de piesă sau o denumire ca să cauți.</p>
    @elseif($products->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Niciun rezultat pentru „{{ $query }}”.
            @if($vehicle && $onlyForMyVehicle) Încearcă fără filtrul de compatibilitate. @endif
        </p>
    @else
        <p class="text-sm text-stone-500">{{ $products->total() }} rezultate</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $product)
                @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $vehicle ? $verdicts($product) : null])
            @endforeach
        </div>

        <div>{{ $products->links() }}</div>
    @endif
</div>
