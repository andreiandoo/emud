<x-storefront.account active="garage" :title="$vehicle->nickname ?: $vehicle->label()" kicker="Garajul meu">
    <x-seo :title="$vehicle->label()" :index="false" :follow="false" />

    <x-slot:actions>
        <a href="{{ route('customer.garage') }}" class="st-btn st-btn--ghost st-btn--sm"><x-storefront.icon name="arrow-left" /> Garajul meu</a>
        <a href="{{ $vehicle->collection?->url() ?? route('storefront.home') }}" class="st-btn st-btn--sm">Caută piese pentru ea <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
    </x-slot:actions>

    @if($status)
        <p class="mb-6 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
            <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $status }}
        </p>
    @endif

    {{-- ============================================================ The car --}}
    @php($configuration = $vehicle->configuration)
    <section class="grid overflow-hidden rounded-[3px] bg-white lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <div class="st-tile relative aspect-[16/10] bg-g2 lg:aspect-auto lg:min-h-[22rem]">
            @if($vehicle->photoUrl())
                <img src="{{ $vehicle->photoUrl() }}" alt="{{ $vehicle->label() }}" class="st-media">
            @else
                <canvas class="st-media" data-st-scene="dusk" data-seed="{{ $vehicle->id * 5 }}" aria-hidden="true"></canvas>
            @endif
            <span class="st-shade"></span>

            <div class="absolute inset-x-4 bottom-4 flex flex-wrap items-center gap-3">
                <label class="st-btn st-btn--ghost st-btn--sm cursor-pointer bg-g0/40 backdrop-blur">
                    <x-storefront.icon name="camera" />
                    {{ $vehicle->photo_path ? 'Schimbă poza' : 'Adaugă o poză' }}
                    <input type="file" accept="image/*" wire:model="photo" class="sr-only">
                </label>
                <span wire:loading wire:target="photo" class="text-xs font-semibold text-bone">Se încarcă…</span>
                @if($vehicle->photo_path)
                    <button type="button" wire:click="removePhoto" wire:confirm="Ștergi poza mașinii?" class="text-xs font-semibold text-bone underline underline-offset-2">Șterge poza</button>
                @endif
            </div>

            @error('photo')
                <p class="absolute inset-x-4 top-4 rounded-[3px] bg-red-50 px-3 py-2 text-xs text-red-800">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid content-start gap-5 p-6 sm:p-8">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $vehicle->make?->name }} · {{ $vehicle->year }}</p>
                <h2 class="mt-1.5 font-display text-[1.9rem] font-semibold leading-none tracking-[-.02em]">{{ $vehicle->label() }}</h2>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    @if($vehicle->registration_number)
                        <span class="rounded-[2px] border border-line2 px-2 py-0.5 font-mono text-xs uppercase">{{ $vehicle->registration_number }}</span>
                    @endif
                    @if($vehicle->is_primary)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-fit"><x-storefront.icon name="check" class="h-3.5 w-3.5" /> mașina principală</span>
                    @endif
                </div>
                @if($vehicle->vin)
                    <p class="mt-2 font-mono text-[11px] uppercase tracking-[.1em] text-ink2">VIN · {{ $vehicle->vin }}</p>
                @endif
            </div>

            @if($configuration)
                <dl class="grid grid-cols-2 border-t border-line text-sm sm:grid-cols-3">
                    @foreach([
                        ['Motorizare', $configuration->engine?->name],
                        ['Capacitate', $configuration->engine?->displacement_cc ? $configuration->engine->displacement_cc.' cm³' : null],
                        ['Putere', $configuration->engine?->power_kw ? $configuration->engine->power_kw.' kW' : null],
                        ['Combustibil', $configuration->engine?->fuel_type],
                        ['Tracțiune', $configuration->drive_type],
                        ['Caroserie', $configuration->body_type],
                    ] as [$label, $value])
                        <div class="border-b border-line py-2.5 pr-3">
                            <dt class="font-mono text-[10.5px] uppercase tracking-[.1em] text-ink2">{{ $label }}</dt>
                            <dd class="mt-0.5 font-medium">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                {{-- Said plainly rather than shown as empty rows: there is no configuration
                     linked, and blank fields would read as missing data instead of absent link. --}}
                <p class="border-t border-line pt-4 text-sm text-ink2">
                    Nu avem încă o configurație tehnică legată de această mașină. Completeaz-o din seria de șasiu (VIN) în
                    <a href="{{ route('customer.garage') }}" class="font-semibold text-ink underline underline-offset-2">garaj</a>
                    ca să potrivim piesele mai exact.
                </p>
            @endif

            <form wire:submit="saveMileage" class="grid gap-1.5">
                <span class="field-label">Kilometraj curent</span>
                <div class="flex gap-2">
                    <input type="number" wire:model="mileage_km" min="0" inputmode="numeric" class="max-w-44">
                    <button type="submit" class="st-btn st-btn--outline st-btn--sm">Salvează</button>
                </div>
                @error('mileage_km') <span class="field-error">{{ $message }}</span> @enderror
                @if($vehicle->mileage_recorded_on)
                    <p class="text-xs text-ink2">Ultima citire: {{ $vehicle->mileage_recorded_on->format('d/m/Y') }}</p>
                @endif
            </form>
        </div>
    </section>

    {{-- ============================================================ Deadlines, and what is around --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
         x-data="{ modal: false }" @reminder-saved.window="modal = false" @keydown.escape.window="modal = false">
        <section class="grid content-start gap-6 rounded-[3px] bg-white p-6 sm:p-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-2xl font-semibold">Scadențe</h2>
                <button type="button" @click="$wire.startReminder('').then(() => modal = true)" class="st-btn st-btn--ink st-btn--sm">
                    <x-storefront.icon name="plus" /> Adaugă scadență
                </button>
            </div>

            {{-- The deadlines every owner has, each one click away: add it if it is not set, change
                 it if it is. --}}
            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($essentials as $type)
                    @php($set = $byType->get($type->value))
                    @php($state = $set?->status())
                    <li wire:key="essential-{{ $type->value }}" @class([
                        'flex items-center justify-between gap-3 rounded-[3px] border p-3.5',
                        'border-dashed border-line2' => $set === null,
                        'border-red-300 bg-red-50' => $state === 'overdue',
                        'border-amber-300 bg-amber-50' => $state === 'due_soon',
                        'border-line bg-light' => $set !== null && ! in_array($state, ['overdue', 'due_soon'], true),
                    ])>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ $type->label() }}</span>
                            <span class="block text-xs text-ink2">
                                @if($set?->due_on)
                                    {{ $set->due_on->format('d/m/Y') }}
                                @elseif($set?->due_at_km)
                                    la {{ number_format($set->due_at_km, 0, ',', '.') }} km
                                @elseif($set)
                                    fără dată
                                @else
                                    nesetată
                                @endif
                            </span>
                        </span>
                        <button type="button" @click="$wire.startReminder('{{ $type->value }}').then(() => modal = true)"
                                class="shrink-0 rounded-full border border-line2 px-3 py-1 text-xs font-semibold transition hover:border-ink hover:bg-ink hover:text-light">
                            {{ $set ? 'Modifică' : '+ Adaugă' }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="grid gap-2">
                <h3 class="st-kicker text-ink2">Toate scadențele, în ordinea în care vin</h3>

                @forelse($reminders as $reminder)
                    @php($state = $reminder->status())
                    <div wire:key="reminder-{{ $reminder->id }}" @class([
                        'flex flex-wrap items-center gap-3 rounded-[3px] border p-4 text-sm',
                        'border-red-300 bg-red-50' => $state === 'overdue',
                        'border-amber-300 bg-amber-50' => $state === 'due_soon',
                        'border-line' => ! in_array($state, ['overdue', 'due_soon'], true),
                    ])>
                        <div class="min-w-0 flex-1">
                            <span class="font-semibold">{{ $reminder->type->label() }}</span>
                            @if($reminder->type->isLegal())
                                <span class="ml-1 pill-neutral">obligatoriu</span>
                            @endif

                            <div class="mt-0.5 text-xs text-ink2">
                                @if($reminder->due_on)
                                    Scadent {{ $reminder->due_on->format('d/m/Y') }}
                                    @if($state === 'overdue')
                                        <span class="font-semibold text-red-700">· expirat de {{ abs($reminder->daysRemaining()) }} zile</span>
                                    @elseif($state === 'due_soon')
                                        <span class="font-semibold text-amber-800">· în {{ $reminder->daysRemaining() }} zile</span>
                                    @endif
                                @endif
                                @if($reminder->due_at_km)
                                    · la {{ number_format($reminder->due_at_km, 0, ',', '.') }} km
                                @endif
                                @unless($reminder->due_on || $reminder->due_at_km)
                                    Fără scadență setată
                                @endunless
                            </div>

                            @if($reminder->notes)<p class="mt-1 text-xs text-ink2">{{ $reminder->notes }}</p>@endif
                        </div>

                        <div class="flex items-center gap-3 text-xs">
                            <button type="button" @click="$wire.startReminder('{{ $reminder->type->value }}').then(() => modal = true)" class="font-semibold underline underline-offset-2 hover:text-ink">Modifică</button>
                            <button type="button" wire:click="markDone({{ $reminder->id }})" class="font-semibold underline underline-offset-2 hover:text-fit">Am făcut-o</button>
                            <button type="button" wire:click="removeReminder({{ $reminder->id }})" wire:confirm="Ștergi scadența?" class="text-red-700 underline underline-offset-2">Șterge</button>
                        </div>
                    </div>
                @empty
                    <p class="rounded-[3px] bg-light p-4 text-sm text-ink2">Nu ai setat încă nicio scadență pentru această mașină. Începe cu una de mai sus.</p>
                @endforelse
            </div>
        </section>

        <div class="grid content-start gap-6">
            <section class="grid gap-3 rounded-[3px] bg-white p-6">
                <h2 class="font-display text-xl font-semibold">Service-uri lângă tine</h2>

                @if(! $location['city'] && ! $location['county'])
                    <p class="text-sm text-ink2">
                        Completează orașul în <a href="{{ route('customer.profile') }}" class="font-semibold text-ink underline underline-offset-2">Datele mele</a>
                        și îți recomandăm ateliere din zonă, cu cele care lucrează pe {{ $vehicle->make?->name ?? 'mașina ta' }} primele.
                    </p>
                @elseif($nearby->isEmpty())
                    <p class="text-sm text-ink2">Nu avem încă ateliere listate lângă {{ $location['city'] ?? $location['county'] }}.</p>
                @else
                    <ul class="grid gap-2">
                        @foreach($nearby as $shop)
                            <li wire:key="nearby-{{ $shop->id }}">
                                <a href="{{ $shop->url() }}" class="grid gap-0.5 rounded-[3px] border border-line p-3.5 transition hover:border-ink">
                                    <span class="font-semibold">{{ $shop->name }}</span>
                                    <span class="text-xs text-ink2">{{ $shop->address ?: $shop->city }}@if($shop->address), {{ $shop->city }}@endif</span>
                                    @if($vehicle->make_id && $shop->makes->contains('id', $vehicle->make_id))
                                        <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-fit"><x-storefront.icon name="check" class="h-3.5 w-3.5" /> lucrează pe {{ $vehicle->make?->name }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="grid gap-3 rounded-[3px] bg-white p-6">
                <h2 class="font-display text-xl font-semibold">Piese cumpărate pentru ea</h2>

                @if($history->isEmpty())
                    <p class="text-sm text-ink2">Încă nimic. Piesele apar aici după ce comanzi având această mașină selectată în magazin.</p>
                @else
                    <ul class="divide-y divide-line border-t border-line text-sm">
                        @foreach($history as $line)
                            <li class="grid gap-0.5 py-3">
                                <span class="font-medium">{{ $line->name }} × {{ $line->quantity }}</span>
                                <span class="text-xs text-ink2">
                                    {{ $line->order?->placed_at?->format('d/m/Y') }} ·
                                    <a href="{{ route('storefront.order', $line->order?->checkout_token) }}" class="font-mono underline underline-offset-2">{{ $line->order?->number }}</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- The deadline form, in a dialog: it is filled in once in a while, and on the page it
             would sit between the customer and the list they came to read. --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="reminder-title">
            <div @click="modal = false" class="absolute inset-0 bg-g0/70 backdrop-blur-sm"></div>

            <form wire:submit="addReminder" x-trap.noscroll="modal" class="relative grid w-full max-w-lg gap-5 rounded-t-[3px] bg-light p-6 text-ink shadow-2xl sm:rounded-[3px] sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div class="grid gap-1.5">
                        <p class="st-kicker text-ink2">Scadență</p>
                        <h2 id="reminder-title" class="font-display text-2xl font-semibold">
                            {{ $reminderType !== '' && \App\Enums\ServiceReminderType::tryFrom($reminderType) ? \App\Enums\ServiceReminderType::from($reminderType)->label() : 'Adaugă scadență' }}
                        </h2>
                    </div>
                    <button type="button" @click="modal = false" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide">
                        <x-storefront.icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <label class="block">
                    <span class="field-label">Ce urmărești</span>
                    <select wire:model.live="reminderType">
                        <option value="">Selectează</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('reminderType') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Scadent la data</span>
                        <x-date-input wire:model="reminderDueOn" />
                        @error('reminderDueOn') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">sau la km</span>
                        <input type="number" wire:model="reminderDueAtKm" min="0" inputmode="numeric">
                        @error('reminderDueAtKm') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="block">
                    <span class="field-label">Notă <span class="font-normal normal-case tracking-normal opacity-70">(opțional)</span></span>
                    <input type="text" wire:model="reminderNotes" placeholder="Ex.: poliță la Allianz, ulei 5W-30">
                    @error('reminderNotes') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
                    <button type="submit" class="st-btn st-btn--ink st-btn--sm">
                        <span wire:loading.remove wire:target="addReminder">Salvează scadența</span>
                        <span wire:loading wire:target="addReminder">Se salvează…</span>
                    </button>
                    <button type="button" @click="modal = false" class="text-sm text-ink2 underline underline-offset-2 hover:text-ink">Renunță</button>
                </div>
            </form>
        </div>
    </div>
</x-storefront.account>
