<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-black tracking-tight">Garajul meu</h1>
        <p class="mt-1 text-sm text-stone-600">
            Mașina principală filtrează automat rezultatele din magazin.
        </p>
    </div>

    <section class="space-y-3">
        @forelse($vehicles as $vehicle)
            <div @class([
                'flex flex-wrap items-center gap-3 rounded-xl border bg-white p-4',
                'border-lime-400 ring-1 ring-lime-400' => $vehicle->is_primary,
                'border-stone-200' => ! $vehicle->is_primary,
            ])>
                {{-- The picture comes from the collection this car belongs to, assigned when it
                     was saved. A car the shop has no collection for shows no image rather than a
                     placeholder pretending to be one. --}}
                @if($vehicle->collection?->garageImageUrl())
                    <a href="{{ $vehicle->collection->url() }}" class="shrink-0">
                        <img src="{{ $vehicle->collection->garageImageUrl() }}" alt="{{ $vehicle->collection->name }}"
                             class="h-14 w-20 rounded-lg object-cover">
                    </a>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $vehicle->make?->name }} {{ $vehicle->model?->name }}</span>
                        @if($vehicle->generation)<span class="text-sm text-stone-500">{{ $vehicle->generation->name }}</span>@endif
                        @if($vehicle->is_primary)
                            <span class="rounded-full bg-lime-100 px-2 py-0.5 text-xs font-semibold text-lime-900">Principală</span>
                        @endif
                    </div>
                    <div class="mt-0.5 text-xs text-stone-500">
                        {{ $vehicle->year }}
                        @if($vehicle->nickname) · {{ $vehicle->nickname }} @endif
                        @if($vehicle->registration_number) · {{ $vehicle->registration_number }} @endif
                    </div>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    @if($vehicle->collection)
                        <a href="{{ $vehicle->collection->url() }}" class="text-stone-600 underline hover:text-stone-900">Piese pentru ea</a>
                    @endif
                    <a href="{{ route('customer.garage.vehicle', $vehicle->id) }}" class="font-semibold underline hover:text-stone-900">Detalii</a>
                    @unless($vehicle->is_primary)
                        <button wire:click="makePrimary({{ $vehicle->id }})" class="text-stone-600 underline hover:text-stone-900">Fă principală</button>
                    @endunless
                    <button wire:click="edit({{ $vehicle->id }})" class="text-stone-600 underline hover:text-stone-900">Editează</button>
                    <button wire:click="remove({{ $vehicle->id }})" wire:confirm="Ștergi această mașină din garaj?" class="text-red-600 underline hover:text-red-800">Șterge</button>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-stone-300 p-6 text-sm text-stone-500">
                Nu ai încă nicio mașină salvată. Adaugă una mai jos.
            </p>
        @endforelse
    </section>

    <section class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-4 text-lg font-bold">{{ $editingId ? 'Editează mașina' : 'Adaugă o mașină' }}</h2>

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Marcă</span>
                    <select wire:model.live="makeId" class="w-full rounded-lg border-stone-300 text-sm">
                        <option value="">Selectează</option>
                        @foreach($makes as $make)
                            <option value="{{ $make->id }}">{{ $make->name }}</option>
                        @endforeach
                    </select>
                    @error('makeId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Model</span>
                    <select wire:model.live="modelId" @disabled($models->isEmpty()) class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                        <option value="">{{ $models->isEmpty() ? 'Alege întâi marca' : 'Selectează' }}</option>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('modelId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Generație <span class="font-normal text-stone-400">(opțional)</span></span>
                    <select wire:model.live="generationId" @disabled($generations->isEmpty()) class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                        <option value="">{{ $generations->isEmpty() ? 'Alege întâi modelul' : 'Nu știu / toate' }}</option>
                        @foreach($generations as $generation)
                            <option value="{{ $generation->id }}">{{ $generation->name }} ({{ $generation->year_from }}{{ $generation->year_to ? '–'.$generation->year_to : '+' }})</option>
                        @endforeach
                    </select>
                    @error('generationId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">An fabricație</span>
                    <input type="number" wire:model="year" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('year') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Poreclă <span class="font-normal text-stone-400">(opțional)</span></span>
                    <input type="text" wire:model="nickname" placeholder="Duster-ul de teren" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('nickname') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Număr înmatriculare <span class="font-normal text-stone-400">(opțional)</span></span>
                    <input type="text" wire:model="registration_number" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('registration_number') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
                    {{ $editingId ? 'Salvează modificările' : 'Adaugă în garaj' }}
                </button>
                @if($editingId)
                    <button type="button" wire:click="resetForm" class="text-sm text-stone-600 underline">Renunță</button>
                @endif
            </div>
        </form>
    </section>
</div>
