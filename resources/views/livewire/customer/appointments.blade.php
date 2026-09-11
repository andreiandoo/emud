<x-storefront.account active="appointments" title="Programările mele" intro="Cereri trimise către service-uri din directorul nostru. Service-ul te contactează să confirme ora.">
    <x-seo title="Programările mele" :index="false" />

    <x-slot:actions>
        <a href="{{ route('storefront.services') }}" class="st-btn st-btn--ghost st-btn--sm">Caută un service</a>
    </x-slot:actions>

    @if($appointments->isEmpty())
        <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
            <x-storefront.icon name="calendar" class="h-10 w-10 text-line2" />
            <p class="font-display text-2xl font-semibold">Nu ai trimis nicio cerere de programare.</p>
            <a href="{{ route('storefront.services') }}" class="st-btn st-btn--ink">Caută un service</a>
        </div>
    @else
        <div class="grid gap-3">
            @foreach($appointments as $appointment)
                <article class="grid gap-4 rounded-[3px] border border-line bg-white p-5 sm:grid-cols-[auto_1fr_auto] sm:items-center sm:gap-6" wire:key="appointment-{{ $appointment->id }}">
                    <div class="grid w-16 place-items-center rounded-[3px] bg-light py-2 text-center">
                        @if($appointment->preferred_date)
                            <span class="font-display text-2xl font-semibold leading-none">{{ $appointment->preferred_date->format('d') }}</span>
                            <span class="font-mono text-[10px] uppercase tracking-[.1em] text-ink2">{{ $appointment->preferred_date->format('m.Y') }}</span>
                        @else
                            <x-storefront.icon name="calendar" class="h-6 w-6 text-ink2" />
                        @endif
                    </div>

                    <div class="min-w-0">
                        <a href="{{ $appointment->shop->url() }}" class="font-display text-lg font-semibold hover:underline">{{ $appointment->shop->name }}</a>
                        <p class="text-sm text-ink2">
                            {{ $appointment->service?->name ?? 'Lucrare nespecificată' }} ·
                            {{ $appointment->vehicleLabel() }}
                        </p>
                        <p class="mt-1 text-xs text-ink2">
                            Trimisă {{ $appointment->created_at->format('d/m/Y') }} ·
                            {{ $appointment->preferred_date?->format('d/m/Y') ?? 'oricând' }}, {{ $appointment->preferred_slot->label() }}
                            @if($appointment->order) · comanda {{ $appointment->order->number }} @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-4 sm:flex-col sm:items-end sm:gap-2">
                        <span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span>

                        @if($appointment->status->isOpen())
                            <button type="button" wire:click="cancel({{ $appointment->id }})"
                                    wire:confirm="Retragi cererea trimisă acestui service?"
                                    class="text-xs font-semibold text-red-700 hover:underline">Retrage cererea</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $appointments->links() }}</div>
    @endif
</x-storefront.account>
