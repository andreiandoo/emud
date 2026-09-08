<div class="space-y-8">
    <x-seo :title="$vehicle->label()" :index="false" :follow="false" />

    <nav class="text-xs text-stone-500">
        <a href="{{ route('customer.garage') }}" class="hover:underline">Garajul meu</a>
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $vehicle->label() }}</span>
    </nav>

    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black tracking-tight">{{ $vehicle->nickname ?: $vehicle->label() }}</h1>
            <p class="mt-1 text-sm text-stone-600">
                {{ $vehicle->label() }} · {{ $vehicle->year }}
                @if($vehicle->registration_number) · {{ $vehicle->registration_number }} @endif
                @if($vehicle->is_primary) · <span class="font-semibold text-lime-700">mașina principală</span> @endif
            </p>
        </div>
        <a href="{{ route('storefront.home') }}" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Caută piese pentru ea</a>
    </div>

    @if($status)
        <p class="rounded-lg border border-lime-300 bg-lime-50 p-3 text-sm text-lime-900">{{ $status }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="mb-3 text-lg font-bold">Date tehnice</h2>
            @php($configuration = $vehicle->configuration)
            @if($configuration)
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-stone-500">Motorizare</dt><dd>{{ $configuration->engine?->name ?? '—' }}</dd>
                    <dt class="text-stone-500">Capacitate</dt><dd>{{ $configuration->engine?->displacement_cc ? $configuration->engine->displacement_cc.' cm³' : '—' }}</dd>
                    <dt class="text-stone-500">Putere</dt><dd>{{ $configuration->engine?->power_kw ? $configuration->engine->power_kw.' kW' : '—' }}</dd>
                    <dt class="text-stone-500">Combustibil</dt><dd>{{ $configuration->engine?->fuel_type ?? '—' }}</dd>
                    <dt class="text-stone-500">Tracțiune</dt><dd>{{ $configuration->drive_type ?? '—' }}</dd>
                    <dt class="text-stone-500">Caroserie</dt><dd>{{ $configuration->body_type ?? '—' }}</dd>
                </dl>
            @else
                {{-- Said plainly rather than shown as empty rows: there is no configuration
                     linked, and blank fields would read as missing data instead of absent link. --}}
                <p class="text-sm text-stone-500">
                    Nu avem încă o configurație tehnică legată de această mașină. Adaugă generația
                    din <a href="{{ route('customer.garage') }}" class="font-semibold underline">garaj</a>
                    ca să putem potrivi piesele mai exact.
                </p>
            @endif

            <form wire:submit="saveMileage" class="mt-5 border-t border-stone-100 pt-4">
                <span class="mb-1 block text-xs font-medium text-stone-600">Kilometraj curent</span>
                <div class="flex gap-2">
                    <input type="number" wire:model="mileage_km" min="0" class="w-40 rounded-lg border-stone-300 text-sm">
                    <button type="submit" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold hover:border-stone-900">Salvează</button>
                </div>
                @error('mileage_km') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                @if($vehicle->mileage_recorded_on)
                    <p class="mt-1 text-xs text-stone-500">Ultima citire: {{ $vehicle->mileage_recorded_on->format('d.m.Y') }}</p>
                @endif
            </form>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="mb-3 text-lg font-bold">Scadențe</h2>

            @forelse($reminders as $reminder)
                @php($state = $reminder->status())
                <div @class([
                    'mb-2 flex flex-wrap items-center gap-3 rounded-lg border p-3 text-sm',
                    'border-red-300 bg-red-50' => $state === 'overdue',
                    'border-amber-300 bg-amber-50' => $state === 'due_soon',
                    'border-stone-200' => ! in_array($state, ['overdue', 'due_soon'], true),
                ])>
                    <div class="min-w-0 flex-1">
                        <span class="font-semibold">{{ $reminder->type->label() }}</span>
                        @if($reminder->type->isLegal())
                            <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-semibold">obligatoriu</span>
                        @endif

                        <div class="mt-0.5 text-xs text-stone-600">
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

                        @if($reminder->notes)<p class="mt-1 text-xs text-stone-500">{{ $reminder->notes }}</p>@endif
                    </div>

                    <div class="flex items-center gap-3 text-xs">
                        <button wire:click="markDone({{ $reminder->id }})" class="underline hover:text-stone-900">Am făcut-o</button>
                        <button wire:click="removeReminder({{ $reminder->id }})" class="text-red-600 underline">Șterge</button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Nu ai setat încă nicio scadență pentru această mașină.</p>
            @endforelse

            @if($suggestions)
                <p class="mt-4 text-xs text-stone-500">
                    Sugestii: {{ collect($suggestions)->map(fn ($item) => $item['type']->label())->implode(', ') }}.
                </p>
            @endif

            <form wire:submit="addReminder" class="mt-5 space-y-3 border-t border-stone-100 pt-4">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Ce urmărești</span>
                    <select wire:model="reminderType" class="w-full rounded-lg border-stone-300 text-sm">
                        <option value="">Selectează</option>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('reminderType') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-stone-600">Scadent la data</span>
                        <input type="date" wire:model="reminderDueOn" class="w-full rounded-lg border-stone-300 text-sm">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-stone-600">sau la km</span>
                        <input type="number" wire:model="reminderDueAtKm" min="0" class="w-full rounded-lg border-stone-300 text-sm">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-stone-600">Notă</span>
                        <input type="text" wire:model="reminderNotes" class="w-full rounded-lg border-stone-300 text-sm">
                    </label>
                </div>

                <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-700">Adaugă scadența</button>
            </form>
        </section>
    </div>

    <section class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-3 text-lg font-bold">Piese cumpărate pentru această mașină</h2>

        @if($history->isEmpty())
            <p class="text-sm text-stone-500">
                Încă nimic. Piesele apar aici după ce comanzi având această mașină selectată în magazin.
            </p>
        @else
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach($history as $line)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                        <span class="min-w-0 flex-1">{{ $line->name }} × {{ $line->quantity }}</span>
                        <span class="text-xs text-stone-500">
                            {{ $line->order?->placed_at?->format('d.m.Y') }} ·
                            <a href="{{ route('storefront.order', $line->order?->checkout_token) }}" class="underline">{{ $line->order?->number }}</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
