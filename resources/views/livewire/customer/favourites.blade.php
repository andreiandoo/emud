<x-storefront.account active="favourites" title="Favoritele mele" intro="Piese salvate pentru cont sau pentru o anumită mașină din garaj.">
    <x-seo title="Favoritele mele" :index="false" :follow="false" />

    <div class="mb-8 flex flex-wrap gap-2">
        <button wire:click="$set('vehicle', '')" @class([
            'st-chip',
            'border-ink bg-ink text-light' => $vehicle === '',
            'hover:border-ink' => $vehicle !== '',
        ])>Toate (cont)</button>

        @foreach($vehicles as $garageVehicle)
            <button wire:click="$set('vehicle', '{{ $garageVehicle->id }}')" @class([
                'st-chip',
                'border-ink bg-ink text-light' => (string) $garageVehicle->id === $vehicle,
                'hover:border-ink' => (string) $garageVehicle->id !== $vehicle,
            ])>
                <x-storefront.icon name="car" class="h-4 w-4" /> {{ $garageVehicle->nickname ?: $garageVehicle->label() }}
            </button>
        @endforeach
    </div>

    @if($items->isEmpty())
        <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
            <x-storefront.icon name="heart" class="h-10 w-10 text-line2" />
            <p class="font-display text-2xl font-semibold">Nu ai salvat încă nimic în această listă.</p>
            <p class="max-w-md text-ink2">Apasă „Salvează la favorite” pe pagina unei piese și o găsești aici, pentru mașina aleasă atunci.</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($items as $item)
                @php($image = $item->product->media->first())
                <article class="flex flex-col overflow-hidden rounded-[3px] border border-line bg-white" wire:key="favourite-{{ $item->id }}">
                    <a href="{{ route('storefront.product', $item->product) }}" class="grid aspect-[16/10] place-items-center border-b border-line bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)]">
                        @if($image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}" alt="" loading="lazy" class="h-full w-full object-contain p-5 mix-blend-multiply">
                        @else
                            <x-storefront.icon name="part" class="h-12 w-12 text-line2" />
                        @endif
                    </a>

                    <div class="flex flex-1 flex-col gap-2 p-5">
                        @if($item->product->brand)
                            <span class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $item->product->brand->name }}</span>
                        @endif

                        <a href="{{ route('storefront.product', $item->product) }}" class="font-semibold leading-snug hover:underline">{{ $item->product->name }}</a>

                        <div class="flex flex-wrap items-center gap-2 text-xs text-ink2">
                            @if($item->vehicle)<span>pentru {{ $item->vehicle->nickname ?: $item->vehicle->label() }}</span>@endif
                            @if($item->verdict_when_saved)
                                <span class="pill-neutral">la salvare: {{ $item->verdict_when_saved->label() }}</span>
                            @endif
                        </div>

                        {{-- Reported, not applied: the customer should see that the answer moved,
                             not find a different label with no explanation. --}}
                        @if($changed($item))
                            <p class="text-xs font-semibold text-amber-800">
                                Compatibilitatea s-a schimbat de când ai salvat produsul. Verific-o pe pagina piesei.
                            </p>
                        @endif

                        <div class="mt-auto flex items-center gap-3 border-t border-line pt-4">
                            <label class="min-w-0 flex-1">
                                <span class="sr-only">Mută în lista</span>
                                <select wire:change="moveToVehicle({{ $item->id }}, $event.target.value || null)" class="min-h-10 text-xs">
                                    <option value="" @selected($item->customer_vehicle_id === null)>Lista contului</option>
                                    @foreach($vehicles as $garageVehicle)
                                        <option value="{{ $garageVehicle->id }}" @selected($item->customer_vehicle_id === $garageVehicle->id)>
                                            {{ $garageVehicle->nickname ?: $garageVehicle->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <button wire:click="remove({{ $item->id }})" class="grid h-10 w-10 shrink-0 place-items-center rounded-[3px] text-ink2 transition hover:bg-light hover:text-red-700" aria-label="Șterge">
                                <x-storefront.icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-storefront.account>
