<div>
    <x-admin.page-header title="Comenzi" subtitle="Comenzi client și starea livrării către furnizori.">
        <x-slot:actions>
            <button type="button" wire:click="$refresh" class="btn-secondary">
                <x-admin.icon name="refresh" class="h-4 w-4" /> Reîmprospătează
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="number_format($totals['count'], 0, ',', '.')" label="Comenzi în filtrul curent" />
        <x-admin.stat :value="$totals['revenue']->format()" label="Valoare totală" />
        <x-admin.stat :value="$totals['average']->format()" label="Valoare medie" />
        <x-admin.stat :value="number_format($totals['unpaid'], 0, ',', '.')" label="Neîncasate"
                      :tone="$totals['unpaid'] > 0 ? 'warning' : 'neutral'" />
    </div>

    <x-admin.tabs :tabs="$tabs" :current="$tab" :counts="$counts" field="tab" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Caută comenzi</span>
            <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
            <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Număr comandă sau email">
        </label>

        {{-- Payment state as chips rather than a second dropdown: there are four of them, they are
             the filter reached for most often, and a chip shows its own state without opening. --}}
        <x-admin.filter-chip wire:click="$set('payment', '')" :active="$payment === ''">Orice plată</x-admin.filter-chip>
        @foreach($paymentStates as $key => $label)
            <x-admin.filter-chip wire:click="$set('payment', '{{ $key }}')" :active="$payment === $key">{{ $label }}</x-admin.filter-chip>
        @endforeach

        @if($search !== '' || $payment !== '' || $tab !== 'all')
            <button type="button" wire:click="resetFilters" class="btn-ghost">Golește filtrele</button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="w-10">
                        <input type="checkbox" aria-label="Selectează pagina"
                               @checked(count($selected) > 0 && count($selected) >= $orders->count())
                               wire:click="{{ count($selected) > 0 ? 'clearSelection' : 'selectPage' }}">
                    </th>
                    <th>Comandă</th>
                    <th>Client</th>
                    <th class="text-right">Produse</th>
                    <th class="text-right">Total</th>
                    <th>Plată</th>
                    <th>Livrare</th>
                    <th>Data</th>
                    <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr wire:key="order-{{ $order->id }}" @class(['bg-stone-50' => in_array($order->id, $selected, true)])>
                        <td>
                            <input type="checkbox" value="{{ $order->id }}" wire:model.live="selected"
                                   aria-label="Selectează comanda {{ $order->number }}">
                        </td>

                        <td>
                            <a href="{{ route('admin.orders.edit', $order) }}" class="font-semibold text-stone-900 hover:underline">{{ $order->number }}</a>
                            <div class="text-xs text-stone-400">{{ $tabs[$order->status] ?? $order->status }}</div>
                        </td>

                        <td>
                            <div class="flex items-center gap-2.5">
                                <x-admin.avatar :name="$order->user?->name ?: $order->customer_email" size="sm" />
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-stone-900">{{ $order->user?->name ?: '—' }}</div>
                                    <div class="truncate text-xs text-stone-500">{{ $order->customer_email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="text-right tabular-nums">{{ $order->items_count }}</td>

                        <td class="text-right font-medium tabular-nums">
                            {{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}
                        </td>

                        <td>
                            <x-admin.status :label="$paymentStates[$order->payment_status] ?? $order->payment_status"
                                            :tone="match ($order->payment_status) {
                                                'paid' => 'positive',
                                                'failed' => 'danger',
                                                'refunded' => 'warning',
                                                default => 'neutral',
                                            }" />
                        </td>

                        <td>
                            <x-admin.status :label="match ($order->fulfillment_status) {
                                                'fulfilled' => 'Livrată',
                                                'partial' => 'Parțial',
                                                'returned' => 'Returnată',
                                                default => 'Nelivrată',
                                            }"
                                            :tone="match ($order->fulfillment_status) {
                                                'fulfilled' => 'positive',
                                                'partial' => 'info',
                                                'returned' => 'warning',
                                                default => 'neutral',
                                            }" />
                        </td>

                        <td class="whitespace-nowrap text-stone-500">{{ $order->created_at->format('d.m.Y H:i') }}</td>

                        <td>
                            <x-admin.row-actions>
                                <x-admin.row-action href="{{ route('admin.orders.edit', $order) }}">Deschide comanda</x-admin.row-action>
                                @foreach($bulkStatuses as $status => $label)
                                    @if($order->status !== $status)
                                        <x-admin.row-action wire:click="markOne({{ $order->id }}, '{{ $status }}')">{{ $label }}</x-admin.row-action>
                                    @endif
                                @endforeach
                            </x-admin.row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-0">
                            <x-admin.empty title="Nicio comandă" hint="Nimic nu se potrivește cu filtrele curente." class="border-0" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>

    <x-admin.bulk-bar :count="count($selected)" clear="clearSelection">
        @foreach($bulkStatuses as $status => $label)
            <x-admin.bulk-action wire:click="markAs('{{ $status }}')">{{ $label }}</x-admin.bulk-action>
        @endforeach
    </x-admin.bulk-bar>
</div>
