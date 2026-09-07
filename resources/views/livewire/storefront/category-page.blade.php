<div class="space-y-6">
    {{-- Filtered and paginated views are the same catalogue sliced differently, so the canonical
         always points at the unfiltered first page. --}}
    <x-seo :title="$category->name"
           :description="$category->description ?? 'Piese și accesorii 4x4 din categoria '.$category->name"
           :canonical="route('storefront.category', $category)" />

    <nav class="text-xs text-stone-500">
        <a href="{{ route('storefront.home') }}" class="hover:underline">Acasă</a>
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $category->name }}</span>
    </nav>

    <div>
        <h1 class="text-2xl font-black tracking-tight">{{ $category->name }}</h1>
        @if($category->description)
            <p class="mt-2 max-w-2xl text-sm text-stone-600">{{ $category->description }}</p>
        @endif
    </div>

    @if($children->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($children as $child)
                <a href="{{ route('storefront.category', $child) }}" class="rounded-full border border-stone-300 px-3 py-1 text-sm hover:border-stone-900">
                    {{ $child->name }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-stone-200 bg-white p-4">
        @if($vehicle)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="onlyForMyVehicle" class="rounded border-stone-300">
                Doar pentru <span class="font-semibold">{{ $vehicle->label() }}</span>
            </label>
        @else
            <span class="text-sm text-stone-500">
                <a href="{{ route('storefront.home') }}" class="font-semibold underline">Alege-ți mașina</a>
                ca să vezi doar piesele compatibile.
            </span>
        @endif

        <label class="ml-auto flex items-center gap-2 text-sm">
            <span class="text-stone-600">Marcă</span>
            <select wire:model.live="brand" class="rounded-lg border-stone-300 text-sm">
                <option value="">Toate</option>
                @foreach($brands as $availableBrand)
                    <option value="{{ $availableBrand->slug }}">{{ $availableBrand->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <span class="text-stone-600">Sortare</span>
            <select wire:model.live="sort" class="rounded-lg border-stone-300 text-sm">
                <option value="relevance">Recomandate</option>
                <option value="name">Denumire</option>
                <option value="newest">Cele mai noi</option>
            </select>
        </label>
    </div>

    @if($products->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            @if($vehicle && $onlyForMyVehicle)
                Niciun produs din această categorie nu este marcat compatibil cu {{ $vehicle->label() }}.
                Debifează filtrul ca să vezi toată categoria.
            @else
                Nu există încă produse publicate în această categorie.
            @endif
        </p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($products as $product)
                @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => $vehicle ? $verdicts($product) : null])
            @endforeach
        </div>

        <div>{{ $products->links() }}</div>
    @endif
</div>
