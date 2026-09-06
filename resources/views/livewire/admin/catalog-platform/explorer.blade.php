<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Catalog Explorer</h1>
        <p class="text-sm text-stone-500">Enter from any side: vehicle, MPN, OE/IAM number, identifier or text.</p>
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-4">
        <div class="grid gap-3 lg:grid-cols-[12rem_1fr]">
            <select wire:model.live="mode" class="rounded-lg border border-stone-300 px-3 py-2">
                <option value="everything">Everything</option>
                <option value="vehicles">Vehicles</option>
                <option value="parts">Parts</option>
                <option value="numbers">Part numbers</option>
                <option value="identifiers">Vehicle identifiers</option>
            </select>
            <input wire:model.live.debounce.300ms="search" autofocus class="rounded-lg border border-stone-300 px-4 py-2.5 text-base" placeholder="VIN, OE, MPN, EAN, make, model, engine, part...">
        </div>
    </div>

    @if ($search !== '')
        <div class="grid gap-6 xl:grid-cols-2">
            @if ($vehicles->isNotEmpty())
                <section class="rounded-xl border border-stone-200 bg-white p-4">
                    <h2 class="mb-3 font-bold">Vehicles <span class="text-stone-400">{{ $vehicles->count() }}</span></h2>
                    <div class="divide-y divide-stone-100">
                        @foreach ($vehicles as $vehicle)
                            <a href="{{ route('admin.catalog-platform.vehicles.show', $vehicle) }}" class="block py-3 hover:bg-stone-50">
                                <div class="font-semibold">{{ $vehicle->generation?->model?->make?->name }} {{ $vehicle->generation?->model?->name }} {{ $vehicle->generation?->name }}</div>
                                <div class="text-sm text-stone-500">{{ $vehicle->commercial_name }} · {{ $vehicle->engine?->engine_code }} · {{ $vehicle->power_kw ? $vehicle->power_kw.' kW' : '' }} · {{ $vehicle->eu_type_approval }}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($parts->isNotEmpty())
                <section class="rounded-xl border border-stone-200 bg-white p-4">
                    <h2 class="mb-3 font-bold">Parts <span class="text-stone-400">{{ $parts->count() }}</span></h2>
                    <div class="divide-y divide-stone-100">
                        @foreach ($parts as $part)
                            <a href="{{ route('admin.catalog-platform.parts.show', $part) }}" class="block py-3 hover:bg-stone-50">
                                <div class="font-semibold">{{ $part->brand?->name }} {{ $part->mpn_raw }}</div>
                                <div class="text-sm text-stone-500">{{ $part->name }} · {{ $part->category?->full_path }}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($numbers->isNotEmpty())
                <section class="rounded-xl border border-stone-200 bg-white p-4 xl:col-span-2">
                    <h2 class="mb-3 font-bold">Identifiers / numbers <span class="text-stone-400">{{ $numbers->count() }}</span></h2>
                    <div class="divide-y divide-stone-100">
                        @foreach ($numbers as $number)
                            <div class="py-3">
                                @if ($number instanceof \App\Models\CatalogPartNumber)
                                    <a href="{{ route('admin.catalog-platform.parts.show', $number->part) }}" class="font-semibold hover:underline">{{ $number->scheme }}: {{ $number->number_raw }}</a>
                                    <div class="text-sm text-stone-500">{{ $number->oeMake?->name }} · {{ $number->part?->brand?->name }} {{ $number->part?->mpn_raw }}</div>
                                @else
                                    <a href="{{ route('admin.catalog-platform.vehicles.show', $number->configuration) }}" class="font-semibold hover:underline">{{ $number->scheme }}: {{ $number->value_raw }}</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($vehicles->isEmpty() && $parts->isEmpty() && $numbers->isEmpty())
                <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-500 xl:col-span-2">No results in the selected segment.</div>
            @endif
        </div>
    @endif
</div>
