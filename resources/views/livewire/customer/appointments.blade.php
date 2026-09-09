<div class="space-y-6">
    <x-seo title="Programările mele" :index="false" />

    <header>
        <h1 class="text-2xl font-bold tracking-tight">Programările mele</h1>
        <p class="mt-1 text-sm text-stone-600">Cereri trimise către service-uri din directorul nostru.</p>
    </header>

    @if($appointments->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 p-8 text-center">
            <p class="text-sm text-stone-500">Nu ai trimis nicio cerere de programare.</p>
            <a href="{{ route('storefront.services') }}" class="mt-3 inline-block rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Caută un service</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($appointments as $appointment)
                <div class="rounded-xl border border-stone-200 bg-white p-5" wire:key="appointment-{{ $appointment->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ $appointment->shop->url() }}" class="font-semibold text-stone-900 hover:underline">{{ $appointment->shop->name }}</a>
                            <p class="text-sm text-stone-600">
                                {{ $appointment->service?->name ?? 'Lucrare nespecificată' }} ·
                                {{ $appointment->vehicleLabel() }}
                            </p>
                            <p class="mt-1 text-xs text-stone-500">
                                Trimisă {{ $appointment->created_at->format('d.m.Y') }} ·
                                {{ $appointment->preferred_date?->format('d.m.Y') ?? 'oricând' }}, {{ $appointment->preferred_slot->label() }}
                                @if($appointment->order) · comanda {{ $appointment->order->number }} @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span>

                            @if($appointment->status->isOpen())
                                <button type="button" wire:click="cancel({{ $appointment->id }})"
                                        wire:confirm="Retragi cererea trimisă acestui service?"
                                        class="text-xs font-semibold text-red-700 hover:underline">Retrage cererea</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $appointments->links() }}</div>
    @endif
</div>
