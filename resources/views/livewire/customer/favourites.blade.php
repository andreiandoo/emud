<div class="space-y-6">
    <x-seo title="Favoritele mele" :index="false" :follow="false" />

    <div>
        <h1 class="text-2xl font-black tracking-tight">Favoritele mele</h1>
        <p class="mt-1 text-sm text-stone-600">
            Piese salvate pentru cont sau pentru o anumită mașină din garaj.
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button wire:click="$set('vehicle', '')" @class([
            'rounded-full border px-3 py-1 text-sm',
            'border-stone-900 bg-stone-900 text-white' => $vehicle === '',
            'border-stone-300 hover:border-stone-900' => $vehicle !== '',
        ])>Toate (cont)</button>

        @foreach($vehicles as $garageVehicle)
            <button wire:click="$set('vehicle', '{{ $garageVehicle->id }}')" @class([
                'rounded-full border px-3 py-1 text-sm',
                'border-stone-900 bg-stone-900 text-white' => (string) $garageVehicle->id === $vehicle,
                'border-stone-300 hover:border-stone-900' => (string) $garageVehicle->id !== $vehicle,
            ])>{{ $garageVehicle->nickname ?: $garageVehicle->label() }}</button>
        @endforeach
    </div>

    @if($items->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Nu ai salvat încă nimic în această listă.
        </p>
    @else
        <div class="space-y-3">
            @foreach($items as $item)
                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-stone-200 bg-white p-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('storefront.product', $item->product) }}" class="font-semibold hover:underline">
                            {{ $item->product->name }}
                        </a>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-stone-500">
                            @if($item->product->brand)<span>{{ $item->product->brand->name }}</span>@endif
                            @if($item->vehicle)<span>· pentru {{ $item->vehicle->nickname ?: $item->vehicle->label() }}</span>@endif
                            @if($item->verdict_when_saved)
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 font-semibold">
                                    la salvare: {{ $item->verdict_when_saved->label() }}
                                </span>
                            @endif
                        </div>

                        {{-- Reported, not applied: the customer should see that the answer moved,
                             not find a different label with no explanation. --}}
                        @if($changed($item))
                            <p class="mt-1 text-xs font-semibold text-amber-800">
                                Compatibilitatea s-a schimbat de când ai salvat produsul. Verific-o pe pagina piesei.
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 text-sm">
                        <select wire:change="moveToVehicle({{ $item->id }}, $event.target.value || null)" class="rounded-lg border-stone-300 text-xs">
                            <option value="" @selected($item->customer_vehicle_id === null)>Lista contului</option>
                            @foreach($vehicles as $garageVehicle)
                                <option value="{{ $garageVehicle->id }}" @selected($item->customer_vehicle_id === $garageVehicle->id)>
                                    {{ $garageVehicle->nickname ?: $garageVehicle->label() }}
                                </option>
                            @endforeach
                        </select>
                        <button wire:click="remove({{ $item->id }})" class="text-red-600 underline hover:text-red-800">Șterge</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
