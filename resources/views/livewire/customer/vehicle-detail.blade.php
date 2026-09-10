<x-storefront.account active="garage" :title="$vehicle->nickname ?: $vehicle->label()" kicker="Garajul meu">
    <x-seo :title="$vehicle->label()" :index="false" :follow="false" />

    <x-slot:actions>
        <a href="{{ route('customer.garage') }}" class="st-btn st-btn--ghost st-btn--sm"><x-storefront.icon name="arrow-left" /> Garajul meu</a>
        <a href="{{ $vehicle->collection?->url() ?? route('storefront.home') }}" class="st-btn st-btn--sm">Caută piese pentru ea <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
    </x-slot:actions>

    <p class="-mt-4 mb-8 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink2">
        <span>{{ $vehicle->label() }} · {{ $vehicle->year }}</span>
        @if($vehicle->registration_number)<span class="rounded-[2px] border border-line2 px-2 py-0.5 font-mono text-xs uppercase">{{ $vehicle->registration_number }}</span>@endif
        @if($vehicle->is_primary)<span class="inline-flex items-center gap-1 font-semibold text-fit"><x-storefront.icon name="check" class="h-3.5 w-3.5" /> mașina principală</span>@endif
    </p>

    @if($status)
        <p class="mb-8 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
            <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $status }}
        </p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="grid content-start gap-5 rounded-[3px] bg-white p-6 sm:p-8">
            <h2 class="font-display text-2xl font-semibold">Date tehnice</h2>

            @php($configuration = $vehicle->configuration)
            @if($configuration)
                <dl class="grid grid-cols-2 border-t border-line text-sm">
                    @foreach([
                        ['Motorizare', $configuration->engine?->name],
                        ['Capacitate', $configuration->engine?->displacement_cc ? $configuration->engine->displacement_cc.' cm³' : null],
                        ['Putere', $configuration->engine?->power_kw ? $configuration->engine->power_kw.' kW' : null],
                        ['Combustibil', $configuration->engine?->fuel_type],
                        ['Tracțiune', $configuration->drive_type],
                        ['Caroserie', $configuration->body_type],
                    ] as [$label, $value])
                        <div class="border-b border-line py-3 pr-4">
                            <dt class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $label }}</dt>
                            <dd class="mt-1 font-medium">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                {{-- Said plainly rather than shown as empty rows: there is no configuration
                     linked, and blank fields would read as missing data instead of absent link. --}}
                <p class="text-sm text-ink2">
                    Nu avem încă o configurație tehnică legată de această mașină. Completeaz-o din seria de șasiu (VIN) în
                    <a href="{{ route('customer.garage') }}" class="font-semibold text-ink underline underline-offset-2">garaj</a>
                    ca să potrivim piesele mai exact.
                </p>
            @endif

            @if($vehicle->vin)
                <p class="font-mono text-xs uppercase tracking-[.1em] text-ink2">VIN · {{ $vehicle->vin }}</p>
            @endif

            <form wire:submit="saveMileage" class="grid gap-2 border-t border-line pt-5">
                <span class="field-label">Kilometraj curent</span>
                <div class="flex gap-2">
                    <input type="number" wire:model="mileage_km" min="0" inputmode="numeric" class="max-w-48">
                    <button type="submit" class="st-btn st-btn--outline st-btn--sm">Salvează</button>
                </div>
                @error('mileage_km') <span class="field-error">{{ $message }}</span> @enderror
                @if($vehicle->mileage_recorded_on)
                    <p class="text-xs text-ink2">Ultima citire: {{ $vehicle->mileage_recorded_on->format('d.m.Y') }}</p>
                @endif
            </form>
        </section>

        <section class="grid content-start gap-4 rounded-[3px] bg-white p-6 sm:p-8">
            <h2 class="font-display text-2xl font-semibold">Scadențe</h2>

            @forelse($reminders as $reminder)
                @php($state = $reminder->status())
                <div @class([
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
                                Scadent {{ $reminder->due_on->format('d.m.Y') }}
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
                        <button wire:click="markDone({{ $reminder->id }})" class="font-semibold underline underline-offset-2 hover:text-fit">Am făcut-o</button>
                        <button wire:click="removeReminder({{ $reminder->id }})" class="text-red-700 underline underline-offset-2">Șterge</button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink2">Nu ai setat încă nicio scadență pentru această mașină.</p>
            @endforelse

            @if($suggestions)
                <p class="text-xs text-ink2">
                    Sugestii: {{ collect($suggestions)->map(fn ($item) => $item['type']->label())->implode(', ') }}.
                </p>
            @endif

            <form wire:submit="addReminder" class="grid gap-4 border-t border-line pt-5">
                <label class="block">
                    <span class="field-label">Ce urmărești</span>
                    <select wire:model="reminderType">
                        <option value="">Selectează</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('reminderType') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block">
                        <span class="field-label">Scadent la data</span>
                        <input type="date" wire:model="reminderDueOn">
                    </label>
                    <label class="block">
                        <span class="field-label">sau la km</span>
                        <input type="number" wire:model="reminderDueAtKm" min="0" inputmode="numeric">
                    </label>
                    <label class="block">
                        <span class="field-label">Notă</span>
                        <input type="text" wire:model="reminderNotes">
                    </label>
                </div>

                <div><button type="submit" class="st-btn st-btn--ink st-btn--sm">Adaugă scadența</button></div>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-[3px] bg-white p-6 sm:p-8">
        <h2 class="font-display text-2xl font-semibold">Piese cumpărate pentru această mașină</h2>

        @if($history->isEmpty())
            <p class="mt-3 text-sm text-ink2">
                Încă nimic. Piesele apar aici după ce comanzi având această mașină selectată în magazin.
            </p>
        @else
            <ul class="mt-4 divide-y divide-line border-t border-line text-sm">
                @foreach($history as $line)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-3">
                        <span class="min-w-0 flex-1 font-medium">{{ $line->name }} × {{ $line->quantity }}</span>
                        <span class="text-xs text-ink2">
                            {{ $line->order?->placed_at?->format('d.m.Y') }} ·
                            <a href="{{ route('storefront.order', $line->order?->checkout_token) }}" class="font-mono underline underline-offset-2">{{ $line->order?->number }}</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-storefront.account>
