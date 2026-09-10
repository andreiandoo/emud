@php($panels = [
    'pick' => ['Alege mașina ta', 'car'],
    'vin' => ['Caută după VIN', 'search'],
    'variants' => ['Alege din modele', 'part'],
])

{{-- One Alpine scope for the bar and all three dialogs. The panel a visitor gave up on has to
     be one click from the next one, so they share a single piece of state rather than three. --}}
<div x-data="{ open: null }"
     @keydown.escape.window="open = null"
     @vehicle-picked.window="open = null"
     @open-variant-chooser.window="open = 'variants'"
     x-effect="document.body.style.overflow = open ? 'hidden' : ''">

    <section class="border-b border-stone-200 bg-white">
        <div class="shell flex flex-col gap-4 py-5 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm text-stone-600">
                <span class="font-bold text-stone-900">Găsește ce se potrivește pe mașina ta.</span>
                Spune-ne ce conduci și filtrăm catalogul pentru tine.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach($panels as $key => [$label, $icon])
                    @continue($key === 'variants' && $children->isEmpty())
                    <button type="button" @click="open = '{{ $key }}'"
                            class="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-4 py-2.5 text-sm font-semibold transition hover:border-stone-900 hover:bg-stone-900 hover:text-white">
                        <x-storefront.icon :name="$icon" class="h-4 w-4" />
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($applied !== '')
            <div class="shell pb-5">
                <p class="rounded-lg bg-lime-50 px-4 py-2.5 text-sm font-semibold text-lime-900">
                    Filtrăm pentru {{ $applied }}. Bifează „Doar ce se potrivește” în filtre ca să restrângi lista.
                </p>
            </div>
        @endif
    </section>

    {{-- 1. Pick the car. The manufacturer starts on the collection being viewed, because someone
            already looking at Dacia should not be asked which make they meant. --}}
    <div x-show="open === 'pick'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
         role="dialog" aria-modal="true" aria-label="Alege mașina ta">
        <div @click="open = null" class="absolute inset-0 bg-stone-950/70 backdrop-blur-sm"></div>

        <div class="relative flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-stone-200 p-5">
                <div>
                    <h2 class="text-lg font-bold">Alege mașina ta</h2>
                    <p class="mt-0.5 text-sm text-stone-500">An, producător, model și submodel.</p>
                </div>
                <button type="button" @click="open = null" class="rounded-lg p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900" aria-label="Închide">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <form wire:submit="applyPick" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Producător</span>
                    <select wire:model.live="pickMakeId">
                        <option value="">Alege producătorul</option>
                        @foreach($makes as $make)
                            <option value="{{ $make->id }}">{{ $make->name }}</option>
                        @endforeach
                    </select>
                    @error('pickMakeId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">An</span>
                    <select wire:model.live="pickYear" @disabled($years === [])>
                        <option value="">Toți anii</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    @error('pickYear') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Model</span>
                    <select wire:model.live="pickModelId" @disabled($models->isEmpty())>
                        <option value="">Alege modelul</option>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('pickModelId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Submodel</span>
                    <select wire:model="pickGenerationId" @disabled($generations->isEmpty())>
                        <option value="">Nu știu / toate</option>
                        @foreach($generations as $generation)
                            <option value="{{ $generation->id }}">
                                {{ $generation->name }}{{ $generation->year_from ? ' ('.$generation->year_from.'–'.($generation->year_to ?: 'prezent').')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-stone-500">Poți lăsa gol dacă nu îl știi.</span>
                    @error('pickGenerationId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="w-full rounded-lg bg-stone-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-stone-700">
                    <span wire:loading.remove wire:target="applyPick">Filtrează pentru mașina mea</span>
                    <span wire:loading wire:target="applyPick">Se aplică…</span>
                </button>
            </form>
        </div>
    </div>

    {{-- 2. VIN. --}}
    <div x-show="open === 'vin'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
         role="dialog" aria-modal="true" aria-label="Caută după VIN">
        <div @click="open = null" class="absolute inset-0 bg-stone-950/70 backdrop-blur-sm"></div>

        <div class="relative flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-stone-200 p-5">
                <div>
                    <h2 class="text-lg font-bold">Caută după VIN</h2>
                    <p class="mt-0.5 text-sm text-stone-500">Seria de șasiu, 17 caractere. O găsești în talon, la rubrica E.</p>
                </div>
                <button type="button" @click="open = null" class="rounded-lg p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900" aria-label="Închide">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <form wire:submit="decodeVin" class="space-y-3">
                    <label class="block">
                        <span class="sr-only">Seria de șasiu</span>
                        <input type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false"
                               placeholder="ex. VF1RFB00X12345678"
                               class="text-center font-mono text-lg uppercase tracking-[.2em]">
                        @error('vin') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <button type="submit" class="w-full rounded-lg bg-stone-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-stone-700">
                        <span wire:loading.remove wire:target="decodeVin">Identifică mașina</span>
                        <span wire:loading wire:target="decodeVin">Se caută…</span>
                    </button>
                </form>

                @if($vinMessage !== '')
                    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $vinMessage }}</p>
                @endif

                @if($vinCandidates !== [])
                    <ul class="divide-y divide-stone-100 rounded-lg border border-stone-200">
                        @foreach($vinCandidates as $candidate)
                            <li wire:key="vin-candidate-{{ $candidate['id'] }}">
                                <button type="button" wire:click="chooseCandidate({{ (int) $candidate['id'] }})"
                                        class="block w-full px-4 py-3 text-left transition hover:bg-stone-50">
                                    <span class="block text-sm font-semibold">
                                        {{ collect([$candidate['make'] ?? null, $candidate['model'] ?? null, $candidate['generation'] ?? null])->filter()->implode(' ') }}
                                    </span>
                                    <span class="block text-xs text-stone-500">
                                        {{ collect([$candidate['year'] ?? null, $candidate['engine'] ?? null, $candidate['engine_code'] ?? null])->filter()->implode(' · ') }}
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Said plainly rather than buried: the decode goes to a third party, and the
                     customer is entitled to know before they type their own car's number in. --}}
                <p class="text-xs text-stone-500">
                    Seria este trimisă către baza publică de date a NHTSA (vPIC) pentru decodare. Nu o salvăm.
                </p>
            </div>
        </div>
    </div>

    {{-- 3. The derivatives. Real links rendered server side, so they stay crawlable and reachable
            without JavaScript now that the public grid shows only main collections. --}}
    @if($children->isNotEmpty())
        @php($variantNames = $children->map(fn ($child) => mb_strtolower($child->name))->values()->all())

        <div x-show="open === 'variants'" x-cloak
             x-data="{ q: '', names: @js($variantNames), get matches() { return this.names.filter(n => n.includes(this.q.toLowerCase())).length } }"
             class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
             role="dialog" aria-modal="true" aria-label="Alege din modele">

            <div @click="open = null" class="absolute inset-0 bg-stone-950/70 backdrop-blur-sm"></div>

            <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-stone-200 p-5">
                    <div>
                        <h2 class="text-lg font-bold">Alege din modele</h2>
                        <p class="mt-0.5 text-sm text-stone-500">
                            {{ $children->count() }} {{ $children->count() === 1 ? 'variantă' : 'variante' }} de {{ $collection->name }}.
                        </p>
                    </div>
                    <button type="button" @click="open = null" class="rounded-lg p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900" aria-label="Închide">
                        <x-storefront.icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="border-b border-stone-200 p-5 pb-4">
                    <label class="relative block">
                        <span class="sr-only">Caută modelul</span>
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-stone-400">
                            <x-storefront.icon name="search" class="h-4 w-4" />
                        </span>
                        <input type="search" x-model="q" autocomplete="off"
                               placeholder="Scrie codul sau numele modelului…" class="pl-10">
                    </label>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-3">
                    <ul class="grid gap-1 sm:grid-cols-2">
                        @foreach($children as $child)
                            <li data-name="{{ mb_strtolower($child->name) }}"
                                x-show="q === '' || $el.dataset.name.includes(q.toLowerCase())">
                                <a href="{{ $child->url() }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 transition hover:bg-stone-100">
                                    @if($child->squareImageUrl())
                                        <img src="{{ $child->squareImageUrl() }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-md object-cover">
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold">{{ $child->name }}</span>
                                        @if($child->yearRange())
                                            <span class="block text-xs text-stone-500">{{ $child->yearRange() }}</span>
                                        @endif
                                    </span>
                                    <x-storefront.icon name="chevron-right" class="h-4 w-4 shrink-0 text-stone-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <p x-show="matches === 0" x-cloak class="p-6 text-center text-sm text-stone-500">
                        Niciun model care să se potrivească.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
