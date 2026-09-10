@php($isCategory = $category !== null)
@php($chooserLabel = $isCategory ? 'Alege subcategoria' : 'Alege din modele')
@php($panels = [
    'pick' => ['Alege mașina ta', 'car'],
    'vin' => ['Caută după VIN', 'vin'],
    'variants' => [$chooserLabel, 'grid'],
])

{{-- One Alpine scope for the bar and all three dialogs. The panel a visitor gave up on has to
     be one click from the next one, so they share a single piece of state rather than three.
     The same bar serves a collection (its derivatives behind the third button) and a category
     (its subcategories). --}}
<div x-data="{ open: null }"
     @keydown.escape.window="open = null"
     @vehicle-picked.window="open = null"
     @open-variant-chooser.window="open = 'variants'"
     x-effect="document.body.style.overflow = open ? 'hidden' : ''">

    <section class="border-b border-gl bg-g1 text-bone">
        <div class="shell flex flex-col gap-4 py-5 lg:flex-row lg:items-center lg:justify-between">
            @if($current)
                <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-sm text-mute">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-fit-bright shadow-[0_0_0_4px_rgba(77,184,116,.18)]"></span>
                    Mașina ta:
                    <span class="font-semibold text-bone">{{ $current->label() }}</span>
                    <span class="max-sm:hidden">· o schimbi oricând de aici.</span>
                </p>
            @else
                <p class="text-sm text-mute">
                    <span class="font-semibold text-bone">Găsește ce se potrivește pe mașina ta.</span>
                    @if($isCategory)
                        Spune-ne ce conduci și îți arătăm doar piesele din {{ $category->name }} care i se potrivesc.
                    @else
                        Spune-ne ce conduci și filtrăm catalogul pentru tine.
                    @endif
                </p>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach($panels as $key => [$label, $icon])
                    @continue($key === 'variants' && $choices->isEmpty())
                    <button type="button" @click="open = '{{ $key }}'" class="st-btn st-btn--ghost st-btn--sm">
                        <x-storefront.icon :name="$icon" />
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($applied !== '')
            <div class="shell pb-5">
                <p class="flex items-start gap-2.5 rounded-[3px] border border-fit-bright/30 bg-fit-bright/10 px-4 py-3 text-sm text-bone">
                    <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-fit-bright" />
                    @if($isCategory)
                        <span><span class="font-semibold">Am reținut {{ $applied }}.</span> Lista de mai jos arată acum doar piesele care i se potrivesc.</span>
                    @else
                        <span><span class="font-semibold">Filtrăm pentru {{ $applied }}.</span> Bifează „Doar ce se potrivește” în filtre ca să restrângi lista.</span>
                    @endif
                </p>
            </div>
        @endif
    </section>

    {{-- 1. Pick the car. On a collection the manufacturer starts on the collection being viewed,
            because someone already looking at Dacia should not be asked which make they meant. --}}
    <div x-show="open === 'pick'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6"
         role="dialog" aria-modal="true" aria-label="Alege mașina ta">
        <div @click="open = null" class="absolute inset-0 bg-g0/70 backdrop-blur-sm"></div>

        <div class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-t-[3px] bg-light text-ink shadow-2xl sm:rounded-[3px]">
            <div class="flex items-start justify-between gap-4 border-b border-line p-6">
                <div class="grid gap-2">
                    <p class="st-kicker text-ink2">Mașina ta</p>
                    <h2 class="font-display text-2xl font-semibold">Alege mașina ta</h2>
                    <p class="text-sm text-ink2">An, producător, model și submodel.</p>
                </div>
                <button type="button" @click="open = null" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="applyPick" class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-6" data-lenis-prevent>
                <label class="block">
                    <span class="field-label">Producător</span>
                    <select wire:model.live="pickMakeId">
                        <option value="">Alege producătorul</option>
                        @foreach($makes as $make)
                            <option value="{{ $make->id }}">{{ $make->name }}</option>
                        @endforeach
                    </select>
                    @error('pickMakeId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">An</span>
                    <select wire:model.live="pickYear" @disabled($years === [])>
                        <option value="">Toți anii</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('pickYear') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Model</span>
                    <select wire:model.live="pickModelId" @disabled($models->isEmpty())>
                        <option value="">Alege modelul</option>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('pickModelId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Submodel</span>
                    <select wire:model="pickGenerationId" @disabled($generations->isEmpty())>
                        <option value="">Nu știu / toate</option>
                        @foreach($generations as $generation)
                            <option value="{{ $generation->id }}">
                                {{ $generation->name }}{{ $generation->year_from ? ' ('.$generation->year_from.'–'.($generation->year_to ?: 'prezent').')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="field-hint">Poți lăsa gol dacă nu îl știi.</span>
                    @error('pickGenerationId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="st-btn st-btn--block mt-2">
                    <span wire:loading.remove wire:target="applyPick">Filtrează pentru mașina mea</span>
                    <span wire:loading wire:target="applyPick">Se aplică…</span>
                </button>
            </form>
        </div>
    </div>

    {{-- 2. VIN. --}}
    <div x-show="open === 'vin'" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6"
         role="dialog" aria-modal="true" aria-label="Caută după VIN">
        <div @click="open = null" class="absolute inset-0 bg-g0/70 backdrop-blur-sm"></div>

        <div class="relative flex max-h-[88vh] w-full max-w-xl flex-col overflow-hidden rounded-t-[3px] bg-light text-ink shadow-2xl sm:rounded-[3px]">
            <div class="flex items-start justify-between gap-4 border-b border-line p-6">
                <div class="grid gap-2">
                    <p class="st-kicker text-ink2">Serie de șasiu</p>
                    <h2 class="font-display text-2xl font-semibold">Caută după VIN</h2>
                    <p class="text-sm text-ink2">Seria de șasiu, 17 caractere. O găsești în talon, la rubrica E.</p>
                </div>
                <button type="button" @click="open = null" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-6" data-lenis-prevent>
                <form wire:submit="decodeVin" class="grid gap-3">
                    <label class="block">
                        <span class="sr-only">Seria de șasiu</span>
                        <input type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false"
                               placeholder="ex. VF1RFB00X12345678"
                               class="h-14 text-center font-mono text-lg uppercase tracking-[.2em]">
                        @error('vin') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="st-btn st-btn--block">
                        <span wire:loading.remove wire:target="decodeVin">Identifică mașina</span>
                        <span wire:loading wire:target="decodeVin">Se caută…</span>
                    </button>
                </form>

                @if($vinMessage !== '')
                    <p class="rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">{{ $vinMessage }}</p>
                @endif

                @if($vinCandidates !== [])
                    <ul class="divide-y divide-line overflow-hidden rounded-[3px] border border-line bg-white">
                        @foreach($vinCandidates as $candidate)
                            <li wire:key="vin-candidate-{{ $candidate['id'] }}">
                                <button type="button" wire:click="chooseCandidate({{ (int) $candidate['id'] }})"
                                        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-light">
                                    <span>
                                        <span class="block text-sm font-semibold">
                                            {{ collect([$candidate['make'] ?? null, $candidate['model'] ?? null, $candidate['generation'] ?? null])->filter()->implode(' ') }}
                                        </span>
                                        <span class="block text-xs text-ink2">
                                            {{ collect([$candidate['year'] ?? null, $candidate['engine'] ?? null, $candidate['engine_code'] ?? null])->filter()->implode(' · ') }}
                                        </span>
                                    </span>
                                    <x-storefront.icon name="arrow-right" class="h-4 w-4 shrink-0 text-ink2" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Said plainly rather than buried: the decode goes to a third party, and the
                     customer is entitled to know before they type their own car's number in. --}}
                <p class="text-xs text-ink2">
                    Seria este trimisă către baza publică de date a NHTSA (vPIC) pentru decodare. Nu o salvăm.
                </p>
            </div>
        </div>
    </div>

    {{-- 3. One level narrower: a collection's derivatives or a category's subcategories. Real
            links rendered server side, so they stay crawlable and reachable without JavaScript. --}}
    @if($choices->isNotEmpty())
        @php($choiceNames = $choices->map(fn (array $choice) => mb_strtolower($choice['name']))->values()->all())

        <div x-show="open === 'variants'" x-cloak
             x-data="{ q: '', names: @js($choiceNames), get matches() { return this.names.filter(n => n.includes(this.q.toLowerCase())).length } }"
             class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6"
             role="dialog" aria-modal="true" aria-label="{{ $chooserLabel }}">

            <div @click="open = null" class="absolute inset-0 bg-g0/70 backdrop-blur-sm"></div>

            <div class="relative flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[3px] bg-light text-ink shadow-2xl sm:rounded-[3px]">
                <div class="flex items-start justify-between gap-4 border-b border-line p-6">
                    <div class="grid gap-2">
                        <p class="st-kicker text-ink2">{{ $isCategory ? 'Subcategorii' : 'Variante' }}</p>
                        <h2 class="font-display text-2xl font-semibold">{{ $chooserLabel }}</h2>
                        <p class="text-sm text-ink2">
                            @if($isCategory)
                                Restrânge {{ $category->name }} la ce te interesează.
                            @else
                                {{ $choices->count() }} {{ $choices->count() === 1 ? 'variantă' : 'variante' }} de {{ $collection?->name }}.
                            @endif
                        </p>
                    </div>
                    <button type="button" @click="open = null" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide">
                        <x-storefront.icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="border-b border-line p-6 pb-4">
                    <label class="relative block">
                        <span class="sr-only">{{ $isCategory ? 'Caută subcategoria' : 'Caută modelul' }}</span>
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-ink2">
                            <x-storefront.icon name="search" class="h-4 w-4" />
                        </span>
                        <input type="search" x-model="q" autocomplete="off" class="pl-10"
                               placeholder="{{ $isCategory ? 'Scrie numele subcategoriei…' : 'Scrie codul sau numele modelului…' }}">
                    </label>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-3" data-lenis-prevent>
                    <ul class="grid gap-1 sm:grid-cols-2">
                        @foreach($choices as $choice)
                            <li data-name="{{ mb_strtolower($choice['name']) }}"
                                x-show="q === '' || $el.dataset.name.includes(q.toLowerCase())">
                                <a href="{{ $choice['url'] }}" class="flex items-center gap-3 rounded-[3px] px-3 py-2.5 transition hover:bg-white">
                                    @if($choice['image'])
                                        <img src="{{ $choice['image'] }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-[2px] object-cover">
                                    @else
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[2px] bg-g2 text-sand">
                                            <x-storefront.icon :name="$choice['icon']" class="h-4 w-4" />
                                        </span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold">{{ $choice['name'] }}</span>
                                        @if($choice['meta'])
                                            <span class="block font-mono text-[11px] text-ink2">{{ $choice['meta'] }}</span>
                                        @endif
                                    </span>
                                    <x-storefront.icon name="chevron-right" class="h-4 w-4 shrink-0 text-ink2" />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <p x-show="matches === 0" x-cloak class="p-6 text-center text-sm text-ink2">
                        {{ $isCategory ? 'Nicio subcategorie cu numele ăsta.' : 'Niciun model care să se potrivească.' }}
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
