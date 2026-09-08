<div>
    <x-admin.page-header :title="'Comanda '.$freshOrder->number" subtitle="Plată, dropshipping, AWB și tracking.">
        <x-slot:actions>
            <a href="{{ route('admin.orders.index') }}" class="btn-secondary">← Toate comenzile</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-admin.panel title="Produse" :subtitle="trans_choice(':count produs în comandă|:count produse în comandă', $freshOrder->items->count(), ['count' => $freshOrder->items->count()])">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Produs</th>
                            <th class="text-right">Cantitate</th>
                            <th class="text-right">Total linie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($freshOrder->items as $item)
                            <tr wire:key="item-{{ $item->id }}">
                                <td>
                                    <div class="font-medium text-stone-900">{{ $item->name }}</div>
                                    <div class="text-xs text-stone-500">{{ $item->sku ?: '—' }}</div>
                                </td>
                                <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="text-right font-medium tabular-nums">
                                    {{ \App\Support\Money::of($item->line_total, $freshOrder->currency)->format() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="flex items-baseline justify-between border-t border-stone-200 pt-4 text-base">
                    <span class="font-semibold text-stone-900">Total</span>
                    <span class="font-semibold tabular-nums">{{ \App\Support\Money::of($freshOrder->grand_total, $freshOrder->currency)->format() }}</span>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Tranzacții" subtitle="Încercările de plată înregistrate pentru această comandă.">
                @forelse($freshOrder->payments as $payment)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 py-2.5 text-sm last:border-b-0">
                        <div class="min-w-0">
                            <span class="font-medium text-stone-900">{{ $payment->provider->name }}</span>
                            <span class="ml-2 font-mono text-xs text-stone-500">{{ $payment->external_id ?: '—' }}</span>
                        </div>

                        <x-admin.status :label="ucfirst($payment->status)" :tone="match ($payment->status) {
                            'succeeded', 'paid' => 'positive',
                            'failed' => 'danger',
                            'refunded' => 'warning',
                            default => 'neutral',
                        }" />
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Nicio tranzacție.</p>
                @endforelse
            </x-admin.panel>

            <x-admin.panel title="Expedieri" subtitle="AWB-uri generate și evenimentele raportate de curier.">
                @forelse($freshOrder->shipments as $shipment)
                    <div class="rounded-xl bg-stone-50 p-4" wire:key="shipment-{{ $shipment->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-stone-900">{{ $shipment->provider->name }}</div>
                                <div class="font-mono text-xs text-stone-500">AWB {{ $shipment->awb_number }}</div>
                                <x-admin.status class="mt-1" :label="ucfirst($shipment->status)" :tone="match ($shipment->status) {
                                    'delivered' => 'positive',
                                    'failed', 'returned' => 'danger',
                                    default => 'info',
                                }" />
                            </div>

                            <button type="button" wire:click="refreshTracking({{ $shipment->id }})" class="btn-secondary">
                                <x-admin.icon name="refresh" class="h-4 w-4" /> Actualizează tracking
                            </button>
                        </div>

                        @if($shipment->events->isNotEmpty())
                            <ul class="mt-3 space-y-1 border-t border-stone-200 pt-3 text-xs text-stone-600">
                                @foreach($shipment->events as $event)
                                    <li class="flex gap-2">
                                        <span class="shrink-0 tabular-nums text-stone-400">{{ $event->occurred_at->format('d.m.Y H:i') }}</span>
                                        <span>{{ $event->description ?: $event->status }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Nicio expediere generată încă.</p>
                @endforelse

                @if($providers->isNotEmpty())
                    <form wire:submit="createAwb" class="grid items-end gap-4 border-t border-stone-200 pt-5 md:grid-cols-4">
                        <label class="block">
                            <span class="field-label">Curier</span>
                            <select wire:model="shippingProviderId">
                                @foreach($providers as $provider)
                                    <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                                @endforeach
                            </select>
                            @error('shippingProviderId') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Greutate (kg)</span>
                            <input type="number" step="0.1" min="0.1" wire:model="weightKg">
                            @error('weightKg') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Colete</span>
                            <input type="number" min="1" wire:model="pieces">
                            @error('pieces') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <button type="submit" class="btn-primary">Generează AWB</button>
                    </form>
                @else
                    <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        Activează și configurează un curier în <a href="{{ route('admin.settings') }}?tab=commerce" class="underline">setări</a> înainte de generarea AWB.
                    </p>
                @endif
            </x-admin.panel>
        </div>

        <aside class="space-y-8">
            <x-admin.section title="Stare">
                <x-admin.definition cols="value" :rows="[
                    'Comandă' => ucfirst($freshOrder->status),
                    'Plată' => ucfirst($freshOrder->payment_status),
                    'Livrare' => ucfirst($freshOrder->fulfillment_status),
                    'Plasată' => $freshOrder->created_at?->format('d.m.Y H:i'),
                ]" />
            </x-admin.section>

            <x-admin.section title="Client">
                <p class="text-sm text-stone-700">
                    {{ $freshOrder->customer_email }}
                    @if($freshOrder->customer_phone)<br>{{ $freshOrder->customer_phone }}@endif
                </p>
            </x-admin.section>

            @if($freshOrder->shippingAddress)
                <x-admin.section title="Adresă de livrare">
                    <p class="text-sm leading-relaxed text-stone-700">
                        {{ $freshOrder->shippingAddress->first_name }} {{ $freshOrder->shippingAddress->last_name }}<br>
                        {{ $freshOrder->shippingAddress->line_1 }}<br>
                        {{ $freshOrder->shippingAddress->city }}, {{ $freshOrder->shippingAddress->county }}
                    </p>
                </x-admin.section>
            @endif

            @if($freshOrder->customer_note)
                <x-admin.section title="Notă de la client">
                    <p class="rounded-lg bg-stone-50 p-3 text-sm text-stone-700">{{ $freshOrder->customer_note }}</p>
                </x-admin.section>
            @endif
        </aside>
    </div>
</div>
