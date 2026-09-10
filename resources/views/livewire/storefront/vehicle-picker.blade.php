{{-- The home page finder, sitting on the glass band at the bottom of the hero. Three ways in:
     the car itself, its VIN, or a part number.

     The three steps are buttons that open upward into lists, as the prototype drew them, rather
     than the browser's own selects: those cannot be styled past their closed state, and on the
     graphite band they opened as a white system menu. Each list is a real listbox of buttons, so
     Tab, Enter and Escape keep working. Choosing a make opens the models straight away. --}}
@php($selectedMake = $makes->firstWhere('id', $this->makeId))
@php($selectedModel = $models->firstWhere('id', $this->modelId))
@php($selectedGeneration = $generations->firstWhere('id', $this->generationId))
@php($generationValue = $selectedGeneration
        ? $selectedGeneration->name.($selectedGeneration->year_from ? ' · '.$selectedGeneration->year_from.($selectedGeneration->year_to ? '–'.$selectedGeneration->year_to : '+') : '')
        : null)
@php($steps = [
    ['makeId', 'Marcă', $makes, $selectedMake?->name, 'Selectează marca', 'Caută marca'],
    ['modelId', 'Model', $models, $selectedModel?->name, $models->isEmpty() ? 'Alege întâi marca' : 'Selectează modelul', 'Caută modelul'],
    ['generationId', 'Generație · opțional', $generations, $generationValue, $generations->isEmpty() ? 'Alege întâi modelul' : 'Toate generațiile', 'Caută generația'],
])

<div x-data="{ tab: 'car', open: null }" @keydown.escape.window="open = null"
     class="grid gap-4 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center lg:gap-5">
    <div class="flex items-center gap-4 lg:grid lg:gap-2 lg:border-r lg:border-gl lg:pr-5">
        <span class="font-mono text-[10.5px] uppercase tracking-[.1em] text-mute">Caută după</span>

        <div class="flex rounded-full border border-gl2 p-[3px]" role="tablist" aria-label="Caută după">
            @foreach(['car' => 'Mașină', 'vin' => 'VIN', 'code' => 'Cod piesă'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'; open = null" :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                        class="h-8 whitespace-nowrap rounded-full px-3.5 text-[13px] font-medium transition"
                        :class="tab === '{{ $key }}' ? 'bg-bone text-ink' : 'text-mute hover:text-bone'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="min-w-0">
        <div x-show="tab === 'car'" @click.outside="open = null" class="grid gap-2.5 sm:grid-cols-3">
            @foreach($steps as $index => [$field, $label, $options, $value, $placeholder, $searchLabel])
                <div class="relative min-w-0" x-data="{ q: '' }" wire:key="picker-step-{{ $field }}">
                    <button type="button" aria-haspopup="listbox" @disabled($options->isEmpty())
                            :aria-expanded="open === '{{ $field }}' ? 'true' : 'false'"
                            @click="open = open === '{{ $field }}' ? null : '{{ $field }}'; q = ''"
                            @class(['st-step', 'is-done' => $value !== null])>
                        <b>{{ $index + 1 }}</b>
                        <span class="grid min-w-0 leading-tight">
                            <span class="font-mono text-[10.5px] uppercase tracking-[.1em] text-mute">{{ $label }}</span>
                            <span @class(['truncate text-[15.5px] font-semibold', 'text-bone' => $value !== null, 'text-[#cfcdc6]' => $value === null])>{{ $value ?? $placeholder }}</span>
                        </span>
                        <x-storefront.icon name="chevron-down" class="h-4 w-4 shrink-0 text-mute" />
                    </button>

                    @if($options->isNotEmpty())
                        <div x-show="open === '{{ $field }}'" x-cloak class="st-pop"
                             x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
                             x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0">
                            {{-- A long list gets a filter, focused on open for a mouse and keyboard; on a
                                 phone the list is short enough to scroll, and a keyboard would cover it. --}}
                            @if($options->count() > 10)
                                <label class="flex items-center gap-2.5 border-b border-gl px-3.5 py-2">
                                    <x-storefront.icon name="search" class="h-4 w-4 shrink-0 text-mute" />
                                    <span class="sr-only">{{ $searchLabel }}</span>
                                    <input type="text" x-model="q" autocomplete="off" spellcheck="false" placeholder="{{ $searchLabel }}…"
                                           x-effect="if (open === '{{ $field }}' && window.matchMedia('(pointer: fine)').matches) $nextTick(() => $el.focus({ preventScroll: true }))"
                                           class="h-8 min-h-0 flex-1 rounded-none border-0 bg-transparent p-0 text-sm text-bone placeholder:text-mute2 focus:shadow-none focus:ring-0">
                                </label>
                            @endif

                            <ul class="grid max-h-[18.5rem] gap-0.5 overflow-y-auto p-1.5" role="listbox" aria-label="{{ $label }}" data-lenis-prevent>
                                @if($field === 'generationId')
                                    <li x-show="q === ''">
                                        <button type="button" role="option" aria-selected="{{ $this->generationId === null ? 'true' : 'false' }}"
                                                @click="$wire.set('generationId', null); open = null">
                                            <span>Toate generațiile</span>
                                            <span class="shrink-0 font-mono text-xs text-mute">nu știu</span>
                                        </button>
                                    </li>
                                @endif

                                @foreach($options as $option)
                                    @php($isSelected = $this->{$field} === $option->id)
                                    <li data-name="{{ mb_strtolower($option->name) }}" x-show="q === '' || $el.dataset.name.includes(q.toLowerCase())">
                                        <button type="button" role="option" aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                                                @if($field === 'makeId')
                                                    @click="open = null; $wire.set('makeId', {{ $option->id }}).then(() => open = 'modelId')"
                                                @else
                                                    @click="open = null; $wire.set('{{ $field }}', {{ $option->id }})"
                                                @endif>
                                            <span class="truncate">{{ $option->name }}</span>
                                            @if($field === 'generationId' && $option->year_from)
                                                <span class="shrink-0 font-mono text-xs text-mute">{{ $option->year_from }}{{ $option->year_to ? '–'.$option->year_to : '+' }}</span>
                                            @elseif($isSelected)
                                                <x-storefront.icon name="check" class="h-4 w-4 shrink-0 text-signal" />
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <form x-show="tab === 'vin'" x-cloak wire:submit="decodeVin" class="flex flex-col gap-2.5 sm:flex-row">
            <label class="flex h-[3.875rem] min-w-0 flex-1 items-center gap-3 rounded-[3px] border border-white/[.12] bg-white/[.04] px-4 transition focus-within:border-bone hover:border-white/35">
                <x-storefront.icon name="vin" class="h-5 w-5 shrink-0 text-mute" />
                <span class="sr-only">Seria de șasiu</span>
                <input type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false" placeholder="Seria de șasiu, 17 caractere (talon, rubrica E)"
                       class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 font-mono text-[15px] uppercase tracking-[.12em] text-bone placeholder:normal-case placeholder:tracking-normal placeholder:text-mute2 focus:shadow-none focus:ring-0">
            </label>
            <button type="submit" class="st-btn">
                <span wire:loading.remove wire:target="decodeVin">Identifică mașina</span>
                <span wire:loading wire:target="decodeVin">Se caută…</span>
            </button>
        </form>

        <form x-show="tab === 'code'" x-cloak action="{{ route('storefront.search') }}" method="get" class="flex flex-col gap-2.5 sm:flex-row">
            <label class="flex h-[3.875rem] min-w-0 flex-1 items-center gap-3 rounded-[3px] border border-white/[.12] bg-white/[.04] px-4 transition focus-within:border-bone hover:border-white/35">
                <x-storefront.icon name="search" class="h-5 w-5 shrink-0 text-mute" />
                <span class="sr-only">Cod piesă sau MPN</span>
                <input type="search" name="q" autocomplete="off" placeholder="Cod piesă, MPN sau denumire"
                       class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 text-[15px] text-bone placeholder:text-mute2 focus:shadow-none focus:ring-0">
            </label>
            <button type="submit" class="st-btn">Caută <x-storefront.icon name="arrow-right" class="st-arrow" /></button>
        </form>
    </div>

    <div x-show="tab === 'car'" class="flex items-center justify-between gap-4 lg:justify-end">
        @if($this->makeId)
            <button type="button" wire:click="clear" class="text-xs text-mute underline underline-offset-2 transition hover:text-bone">Renunță la mașină</button>
        @endif

        <button type="button" wire:click="apply" @disabled(! $this->modelId) class="st-btn max-sm:flex-1">
            <span wire:loading.remove wire:target="apply">Arată piesele</span>
            <span wire:loading wire:target="apply">Se aplică…</span>
            <x-storefront.icon name="arrow-right" class="st-arrow" wire:loading.remove wire:target="apply" />
        </button>
    </div>

    @if($errors->hasAny(['makeId', 'modelId', 'vin']) || $vinMessage !== '' || $vinCandidates !== [])
        <div class="grid gap-2 lg:col-span-3">
            @error('makeId') <p class="text-sm text-signal2">{{ $message }}</p> @enderror
            @error('modelId') <p class="text-sm text-signal2">{{ $message }}</p> @enderror
            @error('vin') <p class="text-sm text-signal2">{{ $message }}</p> @enderror

            @if($vinMessage !== '')
                <p class="text-sm text-bone">{{ $vinMessage }}</p>
            @endif

            @if($vinCandidates !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach($vinCandidates as $candidate)
                        <button type="button" wire:key="picker-vin-{{ $candidate['id'] }}" wire:click="chooseCandidate({{ (int) $candidate['id'] }})" class="st-chip text-bone">
                            {{ collect([$candidate['make'] ?? null, $candidate['model'] ?? null, $candidate['generation'] ?? null, $candidate['year'] ?? null])->filter()->implode(' ') }}
                        </button>
                    @endforeach
                </div>
            @endif

            <p x-show="tab === 'vin'" class="text-xs text-mute">Seria este trimisă către baza publică de date a NHTSA (vPIC) pentru decodare. Nu o salvăm.</p>
        </div>
    @endif
</div>
