<div>
    <x-admin.page-header title="Cereri de programare"
                         subtitle="Lead-urile trimise service-urilor din director: ce a intrat, când și ce s-a răspuns.">
        <x-slot:actions>
            <a href="{{ route('admin.service-shops.index') }}" class="btn-secondary">← Service auto</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($error)
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $error }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <x-admin.filter-chip wire:click="$set('status', '')" :active="$status === ''">Toate</x-admin.filter-chip>

        @foreach($statuses as $option)
            <x-admin.filter-chip wire:click="$set('status', '{{ $option->value }}')"
                                 :active="$status === $option->value"
                                 :count="$counts[$option->value] ?? 0">{{ $option->label() }}</x-admin.filter-chip>
        @endforeach

        <label class="ml-auto w-full sm:w-64">
            <span class="sr-only">Service</span>
            <select wire:model.live="shop">
                <option value="">Toate service-urile</option>
                @foreach($shops as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
            </select>
        </label>
    </div>

    @if($appointments->isEmpty())
        <x-admin.empty title="Nicio cerere" hint="Cererile trimise de clienți din fișele publice apar aici." />
    @else
        <div class="space-y-3">
            @foreach($appointments as $appointment)
                <div class="card-padded" wire:key="appointment-{{ $appointment->id }}">
                    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr_auto]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.service-shops.edit', $appointment->shop) }}" class="font-semibold text-stone-900 hover:underline">
                                    {{ $appointment->shop->name }}
                                </a>
                                <span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span>
                            </div>

                            <p class="mt-2 text-sm text-stone-700">
                                {{ $appointment->service?->name ?? 'Lucrare nespecificată' }} · {{ $appointment->vehicleLabel() }}
                            </p>

                            @if($appointment->message)
                                <p class="mt-2 rounded-lg bg-stone-50 p-3 text-sm text-stone-600">{{ $appointment->message }}</p>
                            @endif

                            @if($appointment->order)
                                {{-- The most valuable kind of lead: the workshop knows exactly what
                                     it will be fitting. --}}
                                <p class="mt-2 text-xs font-semibold text-emerald-700">
                                    Pentru comanda
                                    <a href="{{ route('admin.orders.edit', $appointment->order) }}" class="underline">{{ $appointment->order->number }}</a>
                                </p>
                            @endif
                        </div>

                        <div class="text-sm">
                            <x-admin.definition :rows="[
                                'Client' => $appointment->customer_name,
                                'Telefon' => $appointment->customer_phone,
                                'Email' => $appointment->customer_email,
                                'Când' => ($appointment->preferred_date?->format('d.m.Y') ?? 'oricând').' · '.$appointment->preferred_slot->label(),
                                'Trimisă' => $appointment->created_at->format('d.m.Y H:i'),
                            ]" />
                        </div>

                        <div class="w-full space-y-2 xl:w-56">
                            @if($appointment->status->allowedNext() !== [])
                                <label class="block">
                                    <span class="field-label">Notă internă</span>
                                    <textarea wire:model="notes.{{ $appointment->id }}" rows="2" class="text-xs"></textarea>
                                </label>

                                @foreach($appointment->status->allowedNext() as $next)
                                    <button type="button" wire:click="advance({{ $appointment->id }}, '{{ $next->value }}')"
                                            @class(['w-full', 'btn-primary' => $loop->first, 'btn-secondary' => ! $loop->first])>
                                        {{ $next->label() }}
                                    </button>
                                @endforeach
                            @else
                                <p class="text-xs text-stone-400">Închisă {{ $appointment->responded_at?->format('d.m.Y') }}</p>

                                @if($appointment->internal_note)
                                    <p class="rounded-lg bg-stone-50 p-2 text-xs text-stone-600">{{ $appointment->internal_note }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $appointments->links() }}</div>
    @endif
</div>
