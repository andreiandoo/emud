{{-- Alpine holds only the open/closed state and the tab, so a Livewire re-render (choosing a
     make, applying a vehicle) leaves the panel exactly where the customer left it. The search
     panel, the home page and any "choose your car" button open it through the
     open-vehicle-selector event.

     `opening` exists because of that event: it is fired from a click, and the same click then
     reaches the document, where click.outside would close the panel it had just opened. It is
     cleared once that click has finished travelling. data-picking on the bar makes a bar hidden
     by scrolling come back, since the panel hangs from it. --}}
<div x-data="{ open: false, tab: 'car', opening: false }" class="relative"
     x-effect="$el.closest('[data-st-header]')?.toggleAttribute('data-picking', open)"
     @keydown.escape.window="open = false"
     @open-vehicle-selector.window="
         open = true;
         tab = $event.detail?.tab ?? 'car';
         opening = true;
         setTimeout(() => opening = false);
         if (tab === 'vin') setTimeout(() => $refs.vin?.focus({ preventScroll: true }), 250)"
     @vehicle-changed.window="open = false"
     @click.outside="if (! opening) open = false">
    <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog"
            class="flex h-[2.875rem] items-center gap-2.5 rounded-[3px] border border-white/15 px-3 text-left text-bone transition hover:border-bone">
        <x-storefront.icon name="car" class="h-5 w-5 shrink-0" />

        @if($selected)
            <span class="h-2 w-2 shrink-0 rounded-full bg-fit-bright shadow-[0_0_0_4px_rgba(77,184,116,.18)]"></span>
        @endif

        <span class="hidden min-w-0 leading-tight xl:block">
            @if($selected)
                <span class="block font-mono text-[10.5px] uppercase tracking-[.08em] text-mute">Mașina ta</span>
                <span class="block max-w-40 truncate text-sm font-semibold">{{ $selected->label() }}</span>
            @else
                <span class="block font-mono text-[10.5px] uppercase tracking-[.08em] text-mute">Caută după</span>
                <span class="block text-sm font-semibold">Mașina mea</span>
            @endif
        </span>

        <x-storefront.icon name="chevron-down" class="hidden h-4 w-4 shrink-0 text-mute transition-transform duration-500 xl:block" ::class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.200ms data-lenis-prevent
         class="absolute right-0 top-full z-50 mt-3 max-h-[calc(100vh-var(--st-header-h)-1.5rem)] w-[24rem] overflow-y-auto rounded-[3px] border border-gl2 bg-g1 p-5 text-bone shadow-2xl
                max-sm:fixed max-sm:inset-x-4 max-sm:top-[calc(var(--st-header-h)+0.5rem)] max-sm:w-auto">

        @if($selected)
            <div class="mb-5 flex items-start justify-between gap-3 rounded-[3px] border border-gl2 bg-white/[.03] p-3.5">
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $selected->label() }}</p>
                    <p class="mt-0.5 text-xs text-mute">
                        {{ $selected->isFromGarage() ? 'Din garajul tău' : 'Selectată pentru această vizită' }}
                    </p>
                </div>

                <button type="button" wire:click="clear" class="shrink-0 text-xs font-semibold text-mute underline underline-offset-2 transition hover:text-bone">
                    Renunță
                </button>
            </div>
        @endif

        @auth
            {{-- One click instead of three dropdowns the customer has already filled in once.
                 This is the whole point of the garage, so it comes before the cascade. --}}
            @if($garageVehicles->isNotEmpty())
                <p class="st-kicker mb-3 text-mute">Din garajul tău</p>

                <ul class="mb-4 grid gap-1">
                    @foreach($garageVehicles as $vehicle)
                        @php($isCurrent = $selected?->customerVehicleId === $vehicle->id)
                        <li wire:key="garage-vehicle-{{ $vehicle->id }}">
                            <button type="button" wire:click="chooseFromGarage({{ $vehicle->id }})" @class([
                                'flex w-full items-center gap-3 rounded-[3px] px-3 py-2.5 text-left text-sm transition',
                                'bg-bone text-ink' => $isCurrent,
                                'hover:bg-white/5' => ! $isCurrent,
                            ])>
                                <x-storefront.icon name="car" class="h-4 w-4 shrink-0 {{ $isCurrent ? '' : 'text-mute' }}" />

                                <span class="min-w-0 flex-1 truncate">
                                    {{ $vehicle->make?->name }} {{ $vehicle->model?->name }}
                                    @if($vehicle->year)<span @class(['text-mute' => ! $isCurrent])>· {{ $vehicle->year }}</span>@endif
                                </span>

                                @if($isCurrent)<x-storefront.icon name="check" class="h-4 w-4 shrink-0" />@endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('customer.garage') }}" class="mb-5 flex items-center gap-1.5 text-sm font-semibold text-sand transition hover:text-bone">
                    <x-storefront.icon name="plus" class="h-4 w-4" /> Adaugă o mașină în garaj
                </a>
            @else
                <p class="mb-5 rounded-[3px] bg-white/[.04] p-3.5 text-sm text-mute">
                    Garajul tău este gol.
                    <a href="{{ route('customer.garage') }}" class="font-semibold text-bone underline underline-offset-2">Salvează prima mașină</a>
                    și o vei avea aici la fiecare vizită.
                </p>
            @endif

            <p class="st-kicker mb-3 text-mute">Sau caută altă mașină</p>
        @endauth

        <div class="mb-4 flex rounded-full border border-gl2 p-1 text-[13px]" role="tablist">
            <button type="button" role="tab" @click="tab = 'car'" :aria-selected="tab === 'car' ? 'true' : 'false'"
                    class="flex-1 rounded-full py-1.5 font-medium transition" :class="tab === 'car' ? 'bg-bone text-ink' : 'text-mute hover:text-bone'">
                Marcă și model
            </button>
            <button type="button" role="tab" @click="tab = 'vin'" :aria-selected="tab === 'vin' ? 'true' : 'false'"
                    class="flex-1 rounded-full py-1.5 font-medium transition" :class="tab === 'vin' ? 'bg-bone text-ink' : 'text-mute hover:text-bone'">
                Serie de șasiu (VIN)
            </button>
        </div>

        <div x-show="tab === 'car'">
            <div class="grid gap-2">
                <label class="block">
                    <span class="sr-only">Marcă</span>
                    <select wire:model.live="makeId" class="border-gl2 bg-g0 text-bone focus:border-bone focus:ring-bone">
                        <option value="">Marcă</option>
                        @foreach($makes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Model</span>
                    <select wire:model.live="modelId" @disabled($models->isEmpty()) class="border-gl2 bg-g0 text-bone focus:border-bone focus:ring-bone disabled:bg-g2 disabled:text-mute2">
                        <option value="">{{ $models->isEmpty() ? 'Alege întâi marca' : 'Model' }}</option>
                        @foreach($models as $model)<option value="{{ $model->id }}">{{ $model->name }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="sr-only">Generație</span>
                    <select wire:model.live="generationId" @disabled($generations->isEmpty()) class="border-gl2 bg-g0 text-bone focus:border-bone focus:ring-bone disabled:bg-g2 disabled:text-mute2">
                        <option value="">{{ $generations->isEmpty() ? 'Generație (opțional)' : 'Toate generațiile' }}</option>
                        @foreach($generations as $generation)
                            <option value="{{ $generation->id }}">{{ $generation->name }} ({{ $generation->year_from }}{{ $generation->year_to ? '–'.$generation->year_to : '+' }})</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @error('makeId') <p class="mt-2 text-xs text-signal2">{{ $message }}</p> @enderror
            @error('modelId') <p class="mt-2 text-xs text-signal2">{{ $message }}</p> @enderror
            @error('generationId') <p class="mt-2 text-xs text-signal2">{{ $message }}</p> @enderror

            <button type="button" wire:click="apply" @disabled(! $this->modelId) class="st-btn st-btn--block mt-4">
                <span wire:loading.remove wire:target="apply">Arată piese compatibile</span>
                <span wire:loading wire:target="apply">Se aplică…</span>
            </button>
        </div>

        <div x-show="tab === 'vin'" x-cloak>
            <form wire:submit="decodeVin" class="grid gap-3">
                <label class="block">
                    <span class="mb-2 block text-xs text-mute">17 caractere, în talon la rubrica E.</span>
                    <input x-ref="vin" type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false" placeholder="VF1RFB00X12345678"
                           class="border-gl2 bg-g0 text-center font-mono text-base uppercase tracking-[.18em] text-bone placeholder:text-mute2 focus:border-bone focus:ring-bone">
                </label>

                @error('vin') <p class="text-xs text-signal2">{{ $message }}</p> @enderror

                <button type="submit" class="st-btn st-btn--block">
                    <span wire:loading.remove wire:target="decodeVin">Identifică mașina</span>
                    <span wire:loading wire:target="decodeVin">Se caută…</span>
                </button>
            </form>

            @if($vinMessage !== '')
                <p class="mt-3 rounded-[3px] border border-signal/40 bg-signal/10 px-3.5 py-2.5 text-sm text-bone">{{ $vinMessage }}</p>
            @endif

            @if($vinCandidates !== [])
                <ul class="mt-3 divide-y divide-gl overflow-hidden rounded-[3px] border border-gl2">
                    @foreach($vinCandidates as $candidate)
                        <li wire:key="vin-candidate-{{ $candidate['id'] }}">
                            <button type="button" wire:click="chooseCandidate({{ (int) $candidate['id'] }})" class="block w-full px-3.5 py-2.5 text-left transition hover:bg-white/5">
                                <span class="block text-sm font-semibold">
                                    {{ collect([$candidate['make'] ?? null, $candidate['model'] ?? null, $candidate['generation'] ?? null])->filter()->implode(' ') }}
                                </span>
                                <span class="block text-xs text-mute">
                                    {{ collect([$candidate['year'] ?? null, $candidate['engine'] ?? null, $candidate['engine_code'] ?? null])->filter()->implode(' · ') }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Said plainly rather than buried: the decode goes to a third party, and the customer
                 is entitled to know before they type their own car's number in. --}}
            <p class="mt-3 text-xs text-mute">Seria este trimisă către baza publică de date a NHTSA (vPIC) pentru decodare. Nu o salvăm.</p>
        </div>

        @guest
            {{-- The reason to sign up, stated where the benefit is: the customer has just typed
                 out their car and is about to lose it when the session ends. --}}
            <p class="mt-5 border-t border-gl pt-4 text-xs text-mute">
                <a href="{{ route('customer.login') }}" class="font-semibold text-bone underline underline-offset-2">Autentifică-te</a>
                ca să-ți salvezi mașinile în garaj și să le regăsești la fiecare vizită.
            </p>
        @endguest
    </div>
</div>
