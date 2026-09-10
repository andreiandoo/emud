{{-- The home page finder, sitting on the glass band at the bottom of the hero. Three ways in:
     the car itself, its VIN, or a part number. The three steps are real selects, styled, so the
     keyboard and the phone's own picker keep working. --}}
@php($steps = [
    ['makeId', 'Marcă', $makes, 'Selectează marca', null],
    ['modelId', 'Model', $models, $models->isEmpty() ? 'Alege întâi marca' : 'Selectează modelul', null],
    ['generationId', 'Generație · opțional', $generations, $generations->isEmpty() ? 'Alege întâi modelul' : 'Toate generațiile', true],
])

<div x-data="{ tab: 'car' }" class="grid gap-4 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center lg:gap-5">
    <div class="flex items-center gap-4 lg:grid lg:gap-2 lg:border-r lg:border-gl lg:pr-5">
        <span class="font-mono text-[10.5px] uppercase tracking-[.1em] text-mute">Caută după</span>

        <div class="flex rounded-full border border-gl2 p-[3px]" role="tablist" aria-label="Caută după">
            @foreach(['car' => 'Mașină', 'vin' => 'VIN', 'code' => 'Cod piesă'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}' ? 'true' : 'false'"
                        class="h-8 whitespace-nowrap rounded-full px-3.5 text-[13px] font-medium transition"
                        :class="tab === '{{ $key }}' ? 'bg-bone text-ink' : 'text-mute hover:text-bone'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="min-w-0">
        <div x-show="tab === 'car'" class="grid gap-2.5 sm:grid-cols-3">
            @foreach($steps as $index => [$field, $label, $options, $placeholder, $isGeneration])
                @php($filled = $this->{$field} !== null)
                @php($disabled = $options->isEmpty())

                <label @class([
                    'relative flex h-[3.75rem] min-w-0 items-center gap-3 rounded-[3px] border bg-white/[.04] pl-4 transition',
                    'border-white/[.12] hover:border-white/35 focus-within:border-bone' => ! $disabled,
                    'border-white/[.06] opacity-60' => $disabled,
                ])>
                    <b @class([
                        'grid h-[26px] w-[26px] shrink-0 place-items-center rounded-full border font-mono text-[11.5px] font-medium',
                        'border-signal bg-signal text-ink' => $filled,
                        'border-gl2 text-mute' => ! $filled,
                    ])>{{ $index + 1 }}</b>

                    <span class="grid min-w-0 flex-1">
                        <span class="font-mono text-[10px] uppercase tracking-[.1em] text-mute">{{ $label }}</span>
                        <select wire:model.live="{{ $field }}" @disabled($disabled)
                                class="h-7 w-full min-w-0 cursor-pointer rounded-none border-0 bg-transparent p-0 pr-9 text-[15px] font-semibold text-bone focus:ring-0 disabled:cursor-not-allowed disabled:bg-transparent disabled:text-mute [&_option]:bg-g1 [&_option]:text-bone">
                            <option value="">{{ $placeholder }}</option>
                            @foreach($options as $option)
                                <option value="{{ $option->id }}">
                                    {{ $option->name }}@if($isGeneration && $option->year_from) ({{ $option->year_from }}{{ $option->year_to ? '–'.$option->year_to : '+' }})@endif
                                </option>
                            @endforeach
                        </select>
                    </span>
                </label>
            @endforeach
        </div>

        <form x-show="tab === 'vin'" x-cloak wire:submit="decodeVin" class="flex flex-col gap-2.5 sm:flex-row">
            <label class="flex h-[3.75rem] min-w-0 flex-1 items-center gap-3 rounded-[3px] border border-white/[.12] bg-white/[.04] px-4 focus-within:border-bone">
                <x-storefront.icon name="vin" class="h-5 w-5 shrink-0 text-mute" />
                <span class="sr-only">Seria de șasiu</span>
                <input type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false" placeholder="Seria de șasiu, 17 caractere (talon, rubrica E)"
                       class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 font-mono text-[15px] uppercase tracking-[.12em] text-bone placeholder:normal-case placeholder:tracking-normal placeholder:text-mute2 focus:ring-0">
            </label>
            <button type="submit" class="st-btn">
                <span wire:loading.remove wire:target="decodeVin">Identifică mașina</span>
                <span wire:loading wire:target="decodeVin">Se caută…</span>
            </button>
        </form>

        <form x-show="tab === 'code'" x-cloak action="{{ route('storefront.search') }}" method="get" class="flex flex-col gap-2.5 sm:flex-row">
            <label class="flex h-[3.75rem] min-w-0 flex-1 items-center gap-3 rounded-[3px] border border-white/[.12] bg-white/[.04] px-4 focus-within:border-bone">
                <x-storefront.icon name="search" class="h-5 w-5 shrink-0 text-mute" />
                <span class="sr-only">Cod piesă sau MPN</span>
                <input type="search" name="q" autocomplete="off" placeholder="Cod piesă, MPN sau denumire"
                       class="min-w-0 flex-1 rounded-none border-0 bg-transparent p-0 text-[15px] text-bone placeholder:text-mute2 focus:ring-0">
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
