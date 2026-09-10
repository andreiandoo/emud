<x-storefront.account active="garage" title="Garajul meu" intro="Mașina principală filtrează automat rezultatele din magazin. Salvează-le pe toate și schimbi între ele dintr-un click.">
    <x-slot:actions>
        <a href="#adauga" class="st-btn">Adaugă o mașină <x-storefront.icon name="plus" /></a>
    </x-slot:actions>

    @php($scenes = ['dusk', 'steel', 'sand', 'mud', 'forest', 'snow'])

    <section class="grid gap-5 md:grid-cols-2">
        @forelse($vehicles as $index => $vehicle)
            <article wire:key="garage-{{ $vehicle->id }}" @class([
                'grid overflow-hidden rounded-[3px] border bg-white',
                'border-ink' => $vehicle->is_primary,
                'border-line' => ! $vehicle->is_primary,
            ])>
                {{-- The picture comes from the collection this car belongs to, assigned when it
                     was saved. Without one, a drawn landscape stands in rather than an empty box. --}}
                <div class="st-tile aspect-[16/8] bg-g2">
                    @if($vehicle->collection?->garageImageUrl())
                        <img src="{{ $vehicle->collection->garageImageUrl() }}" alt="{{ $vehicle->collection->name }}" class="st-media">
                    @else
                        <canvas class="st-media" data-st-scene="{{ $scenes[$index % count($scenes)] }}" data-seed="{{ $vehicle->id * 5 }}" aria-hidden="true"></canvas>
                    @endif
                    <span class="st-shade"></span>

                    <div class="absolute inset-x-5 top-4 flex items-start justify-between gap-3">
                        @if($vehicle->is_primary)
                            <span class="inline-flex items-center gap-1.5 rounded-[2px] bg-fit px-2 py-1 text-[10px] font-semibold uppercase tracking-[.1em] text-white">
                                <x-storefront.icon name="check" class="h-3 w-3" /> Principală
                            </span>
                        @else
                            <span></span>
                        @endif

                        @if($vehicle->registration_number)
                            <span class="rounded-[2px] border border-white/40 bg-g0/40 px-2 py-1 font-mono text-[11px] uppercase tracking-[.08em] text-bone backdrop-blur">{{ $vehicle->registration_number }}</span>
                        @endif
                    </div>

                    <div class="absolute inset-x-5 bottom-4 text-bone">
                        <p class="font-display text-[1.9rem] font-semibold leading-none tracking-[-.02em]">{{ $vehicle->nickname ?: $vehicle->make?->name.' '.$vehicle->model?->name }}</p>
                        <p class="mt-1.5 text-sm text-bone/75">
                            {{ $vehicle->make?->name }} {{ $vehicle->model?->name }}@if($vehicle->generation) · {{ $vehicle->generation->name }}@endif · {{ $vehicle->year }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('customer.garage.vehicle', $vehicle->routeSlug()) }}" class="st-btn st-btn--ink st-btn--sm">Detalii</a>
                        @if($vehicle->collection)
                            <a href="{{ $vehicle->collection->url() }}" class="st-btn st-btn--outline st-btn--sm">Piese pentru ea</a>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 text-sm text-ink2">
                        @unless($vehicle->is_primary)
                            <button wire:click="makePrimary({{ $vehicle->id }})" class="underline-offset-2 transition hover:text-ink hover:underline">Fă principală</button>
                        @endunless
                        <button wire:click="edit({{ $vehicle->id }})" class="underline-offset-2 transition hover:text-ink hover:underline">Editează</button>
                        <button wire:click="remove({{ $vehicle->id }})" wire:confirm="Ștergi această mașină din garaj?" class="text-red-700 underline-offset-2 hover:underline">Șterge</button>
                    </div>
                </div>

                @if($vehicle->vin)
                    <p class="border-t border-line px-4 py-2.5 font-mono text-[11px] uppercase tracking-[.1em] text-ink2">VIN · {{ $vehicle->vin }}</p>
                @endif
            </article>
        @empty
            <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center md:col-span-2">
                <x-storefront.icon name="car" class="h-10 w-10 text-line2" />
                <p class="font-display text-2xl font-semibold">Garajul e gol.</p>
                <p class="max-w-md text-ink2">Adaugă prima mașină mai jos. De atunci, magazinul îți arată doar piesele care i se potrivesc.</p>
            </div>
        @endforelse
    </section>

    <section id="adauga" class="mt-14 scroll-mt-32 rounded-[3px] bg-white p-6 sm:p-8" x-data="{ tab: 'vin' }">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-5">
            <div class="grid gap-2">
                <p class="st-kicker text-ink2">{{ $editingId ? 'Editare' : 'Mașină nouă' }}</p>
                <h2 class="font-display text-[1.9rem] font-semibold leading-none tracking-[-.02em]">{{ $editingId ? 'Editează mașina' : 'Adaugă o mașină' }}</h2>
            </div>

            <div class="flex rounded-full border border-line2 p-[3px] text-[13px]" role="tablist">
                <button type="button" role="tab" @click="tab = 'vin'" :aria-selected="tab === 'vin' ? 'true' : 'false'"
                        class="rounded-full px-3.5 py-1.5 font-medium transition" :class="tab === 'vin' ? 'bg-ink text-light' : 'text-ink2 hover:text-ink'">Din seria de șasiu</button>
                <button type="button" role="tab" @click="tab = 'manual'" :aria-selected="tab === 'manual' ? 'true' : 'false'"
                        class="rounded-full px-3.5 py-1.5 font-medium transition" :class="tab === 'manual' ? 'bg-ink text-light' : 'text-ink2 hover:text-ink'">Manual</button>
            </div>
        </div>

        {{-- The VIN path fills the fields below; the customer checks them and saves. --}}
        <div x-show="tab === 'vin'" class="mt-6 grid gap-3">
            <p class="text-sm text-ink2">Seria de șasiu (VIN) are 17 caractere și o găsești în talon, la rubrica E. Completăm marca, modelul, generația și motorizarea.</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <label class="min-w-0 flex-1">
                    <span class="sr-only">Seria de șasiu</span>
                    <input type="text" wire:model="vin" maxlength="17" autocomplete="off" spellcheck="false" placeholder="VF1RFB00X12345678"
                           class="h-12 font-mono text-base uppercase tracking-[.16em]">
                </label>
                <button type="button" wire:click="decodeVin" class="st-btn st-btn--ink">
                    <span wire:loading.remove wire:target="decodeVin">Completează din VIN</span>
                    <span wire:loading wire:target="decodeVin">Se caută…</span>
                </button>
            </div>
            @error('vin') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            @if($vinCandidates !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach($vinCandidates as $candidate)
                        <button type="button" wire:key="garage-vin-{{ $candidate['id'] }}" wire:click="chooseCandidate({{ (int) $candidate['id'] }})" class="st-chip hover:bg-ink hover:text-light">
                            {{ collect([$candidate['make'] ?? null, $candidate['model'] ?? null, $candidate['generation'] ?? null, $candidate['year'] ?? null])->filter()->implode(' ') }}
                        </button>
                    @endforeach
                </div>
            @endif
            <p class="text-xs text-ink2">Seria este trimisă către baza publică de date a NHTSA (vPIC) pentru decodare și se salvează doar în garajul tău.</p>
        </div>

        @if($vinMessage !== '')
            <p class="mt-5 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
                <x-storefront.icon name="vin" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $vinMessage }}
            </p>
        @endif

        <form wire:submit="save" class="mt-6 grid gap-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block">
                    <span class="field-label">Marcă</span>
                    <select wire:model.live="makeId">
                        <option value="">Selectează</option>
                        @foreach($makes as $make)
                            <option value="{{ $make->id }}">{{ $make->name }}</option>
                        @endforeach
                    </select>
                    @error('makeId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Model</span>
                    <select wire:model.live="modelId" @disabled($models->isEmpty())>
                        <option value="">{{ $models->isEmpty() ? 'Alege întâi marca' : 'Selectează' }}</option>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('modelId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Generație <span class="font-normal text-ink2/70">(opțional)</span></span>
                    <select wire:model.live="generationId" @disabled($generations->isEmpty())>
                        <option value="">{{ $generations->isEmpty() ? 'Alege întâi modelul' : 'Nu știu / toate' }}</option>
                        @foreach($generations as $generation)
                            <option value="{{ $generation->id }}">{{ $generation->name }} ({{ $generation->year_from }}{{ $generation->year_to ? '–'.$generation->year_to : '+' }})</option>
                        @endforeach
                    </select>
                    @error('generationId') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <label class="block">
                    <span class="field-label">An fabricație</span>
                    <input type="number" wire:model="year" inputmode="numeric">
                    @error('year') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Poreclă <span class="font-normal text-ink2/70">(opțional)</span></span>
                    <input type="text" wire:model="nickname" placeholder="Duster-ul de teren">
                    @error('nickname') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Număr înmatriculare <span class="font-normal text-ink2/70">(opțional)</span></span>
                    <input type="text" wire:model="registration_number" class="uppercase">
                    @error('registration_number') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">VIN <span class="font-normal text-ink2/70">(opțional)</span></span>
                    <input type="text" wire:model="vin" maxlength="17" class="font-mono uppercase tracking-[.08em]">
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-4 border-t border-line pt-5">
                <button type="submit" class="st-btn">
                    {{ $editingId ? 'Salvează modificările' : 'Adaugă în garaj' }} <x-storefront.icon name="arrow-right" class="st-arrow" />
                </button>
                @if($editingId)
                    <button type="button" wire:click="resetForm" class="text-sm text-ink2 underline underline-offset-2 hover:text-ink">Renunță</button>
                @endif
            </div>
        </form>
    </section>
</x-storefront.account>
