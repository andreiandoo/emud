<div class="space-y-10">
    <section>
        <h1 class="mb-2 text-3xl font-black tracking-tight">Piese și accesorii pentru 4x4</h1>
        <p class="mb-6 max-w-2xl text-stone-600">
            Spune-ne ce mașină ai și îți arătăm doar ce se potrivește pe ea. Off-road, mudding și overlanding.
        </p>

        <livewire:storefront.vehicle-picker />

        @if($vehicle)
            <p class="mt-3 text-sm text-stone-600">
                Rezultatele sunt filtrate pentru <span class="font-semibold text-stone-900">{{ $vehicle->label() }}</span>.
                @if($vehicle->isFromGarage())
                    Mașina vine din garajul tău.
                @endif
            </p>
        @endif
    </section>

    <section>
        <h2 class="mb-4 text-lg font-bold">Categorii</h2>

        @if($categories->isEmpty())
            <p class="rounded-lg border border-dashed border-stone-300 p-6 text-sm text-stone-500">
                Nu există încă nicio categorie vizibilă. Se administrează din panoul de administrare.
            </p>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($categories as $category)
                    <div class="rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-400">
                        <span class="block font-semibold">{{ $category->name }}</span>
                        @if($category->description)
                            <span class="mt-1 block text-xs text-stone-500">{{ Str::limit($category->description, 80) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
