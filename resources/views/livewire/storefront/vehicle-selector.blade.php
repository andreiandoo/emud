{{-- Alpine holds only the open/closed state, so a Livewire re-render (choosing a make, applying
     a vehicle) leaves the panel exactly where the customer left it. --}}
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative">
    <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
            class="flex h-11 items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 text-left transition hover:border-stone-900">
        <x-storefront.icon name="car" class="h-5 w-5 shrink-0 text-stone-500" />

        <span class="hidden min-w-0 leading-tight sm:block">
            @if($selected)
                <span class="block text-[10px] uppercase tracking-wider text-stone-400">Mașina ta</span>
                <span class="block max-w-40 truncate text-sm font-semibold text-stone-900">{{ $selected->label() }}</span>
            @else
                <span class="block text-[10px] uppercase tracking-wider text-stone-400">Caută după</span>
                <span class="block text-sm font-semibold text-stone-900">Mașina mea</span>
            @endif
        </span>

        <x-storefront.icon name="chevron-down" class="h-4 w-4 shrink-0 text-stone-400" ::class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click.outside="open = false"
         class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] rounded-xl border border-stone-200 bg-white p-4 shadow-xl">

        @if($selected)
            <div class="mb-4 flex items-start justify-between gap-3 rounded-lg bg-stone-100 p-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-stone-900">{{ $selected->label() }}</p>
                    <p class="text-xs text-stone-500">
                        {{ $selected->isFromGarage() ? 'Din garajul tău' : 'Selectată pentru această vizită' }}
                    </p>
                </div>

                <button type="button" wire:click="clear" class="shrink-0 text-xs font-semibold text-stone-500 underline hover:text-stone-900">
                    Renunță
                </button>
            </div>
        @endif

        @auth
            {{-- One click instead of three dropdowns the customer has already filled in once.
                 This is the whole point of the garage, so it comes before the cascade. --}}
            @if($garageVehicles->isNotEmpty())
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-stone-500">Din garajul tău</p>

                <ul class="mb-4 space-y-1">
                    @foreach($garageVehicles as $vehicle)
                        @php($isCurrent = $selected?->customerVehicleId === $vehicle->id)
                        <li wire:key="garage-vehicle-{{ $vehicle->id }}">
                            <button type="button" wire:click="chooseFromGarage({{ $vehicle->id }})" @class([
                                'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition',
                                'bg-stone-900 text-white' => $isCurrent,
                                'hover:bg-stone-100' => ! $isCurrent,
                            ])>
                                <x-storefront.icon name="car" class="h-4 w-4 shrink-0 {{ $isCurrent ? '' : 'text-stone-400' }}" />

                                <span class="min-w-0 flex-1 truncate">
                                    {{ $vehicle->make?->name }} {{ $vehicle->model?->name }}
                                    @if($vehicle->year)<span @class(['text-stone-400' => ! $isCurrent])>· {{ $vehicle->year }}</span>@endif
                                </span>

                                @if($isCurrent)<x-storefront.icon name="check" class="h-4 w-4 shrink-0" />@endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('customer.garage') }}" class="mb-4 flex items-center gap-1.5 text-sm font-semibold text-stone-700 hover:text-stone-900">
                    <x-storefront.icon name="plus" class="h-4 w-4" /> Adaugă o mașină în garaj
                </a>
            @else
                <p class="mb-4 rounded-lg bg-stone-100 p-3 text-sm text-stone-600">
                    Garajul tău este gol.
                    <a href="{{ route('customer.garage') }}" class="font-semibold text-stone-900 underline">Salvează prima mașină</a>
                    și o vei avea aici la fiecare vizită.
                </p>
            @endif

            <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-stone-500">Sau caută altă mașină</p>
        @endauth

        <div class="space-y-2">
            <label class="block">
                <span class="sr-only">Marcă</span>
                <select wire:model.live="makeId">
                    <option value="">Marcă</option>
                    @foreach($makes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Model</span>
                <select wire:model.live="modelId" @disabled($models->isEmpty())>
                    <option value="">{{ $models->isEmpty() ? 'Alege întâi marca' : 'Model' }}</option>
                    @foreach($models as $model)<option value="{{ $model->id }}">{{ $model->name }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="sr-only">Generație</span>
                <select wire:model.live="generationId" @disabled($generations->isEmpty())>
                    <option value="">{{ $generations->isEmpty() ? 'Generație (opțional)' : 'Toate generațiile' }}</option>
                    @foreach($generations as $generation)
                        <option value="{{ $generation->id }}">{{ $generation->name }} ({{ $generation->year_from }}{{ $generation->year_to ? '–'.$generation->year_to : '+' }})</option>
                    @endforeach
                </select>
            </label>
        </div>

        @error('makeId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('modelId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('generationId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

        <button type="button" wire:click="apply" @disabled(! $this->modelId)
                class="mt-3 w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700 disabled:cursor-not-allowed disabled:bg-stone-300">
            Arată piese compatibile
        </button>

        @guest
            {{-- The reason to sign up, stated where the benefit is: the customer has just typed
                 out their car and is about to lose it when the session ends. --}}
            <p class="mt-3 border-t border-stone-100 pt-3 text-xs text-stone-500">
                <a href="{{ route('customer.login') }}" class="font-semibold text-stone-900 underline">Autentifică-te</a>
                ca să-ți salvezi mașinile în garaj și să le regăsești la fiecare vizită.
            </p>
        @endguest
    </div>
</div>
