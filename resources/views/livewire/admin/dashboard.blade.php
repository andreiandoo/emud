<div>
    <x-admin.page-header title="Panou general" subtitle="Vânzările lunii, starea comenzilor și sănătatea catalogului." />

    {{-- Two columns: the working surface on the left, the figures rail on the right. The rail
         collapses under the content below xl rather than shrinking, because a 200px-wide chart
         says less than no chart at all. --}}
    <div class="grid gap-8 xl:grid-cols-[1fr_20rem]">
        <div class="space-y-8">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($metrics as $metric)
                    <x-admin.stat :value="$metric['value']" :label="$metric['label']" :tone="$metric['tone'] ?? 'neutral'" />
                @endforeach
            </div>

            <x-admin.section title="Ultimele comenzi">
                <x-slot:aside>
                    <a href="{{ route('admin.orders.index') }}" class="hover:text-stone-900 hover:underline">Toate comenzile</a>
                </x-slot:aside>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Comandă</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th class="text-right">Total</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr wire:key="recent-order-{{ $order->id }}">
                                <td>
                                    <a href="{{ route('admin.orders.edit', $order) }}" class="font-medium text-stone-900 hover:underline">{{ $order->number }}</a>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-admin.avatar :name="$order->user?->name ?: $order->customer_email" size="sm" />
                                        <span class="truncate text-stone-600">{{ $order->user?->name ?: $order->customer_email }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-admin.status :label="ucfirst($order->status)" :tone="match ($order->status) {
                                        'completed' => 'positive',
                                        'cancelled' => 'danger',
                                        'pending' => 'warning',
                                        default => 'info',
                                    }" />
                                </td>
                                <td class="text-right font-medium tabular-nums">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</td>
                                <td class="whitespace-nowrap text-stone-500">{{ $order->created_at->format('d.m.Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <x-admin.empty title="Nicio comandă încă" hint="Prima comandă apare aici imediat ce este plasată." class="border-0" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.section>

            <x-admin.section title="Ultimele sincronizări de furnizori">
                <x-slot:aside>
                    <a href="{{ route('admin.suppliers.index') }}" class="hover:text-stone-900 hover:underline">Furnizori</a>
                </x-slot:aside>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Furnizor</th>
                            <th>Mod</th>
                            <th>Status</th>
                            <th class="text-right">Procesate</th>
                            <th>Pornită</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRuns as $run)
                            <tr wire:key="recent-run-{{ $run->id }}">
                                <td class="font-medium text-stone-900">{{ $run->supplier->name }}</td>
                                <td class="text-stone-600">{{ $run->mode }}</td>
                                <td>
                                    <x-admin.status :label="$run->status->label()" :tone="match (true) {
                                        $run->status->isHealthy() => 'positive',
                                        $run->status->needsAttention() => 'danger',
                                        default => 'info',
                                    }" />
                                </td>
                                <td class="text-right tabular-nums">{{ number_format($run->processed, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap text-stone-500">{{ $run->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <x-admin.empty title="Nicio sincronizare încă" hint="Rulează un import de furnizor pentru a popula lista." class="border-0" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.section>
        </div>

        <aside class="space-y-8">
            <x-admin.section title="Starea comenzilor">
                <x-admin.meter :segments="$orderStates" />
            </x-admin.section>

            <x-admin.section title="Catalog">
                <x-admin.definition :rows="collect($catalogHealth)->map(fn ($value) => number_format($value, 0, ',', '.'))->all()" cols="value" />
            </x-admin.section>

            <x-admin.section title="Scurtături">
                <div class="flex flex-col items-start gap-1">
                    <a href="{{ route('admin.products.create') }}" class="btn-ghost px-0">Produs nou</a>
                    <a href="{{ route('admin.returns.index') }}" class="btn-ghost px-0">Retururi</a>
                    <a href="{{ route('admin.catalog-platform.sources') }}" class="btn-ghost px-0">Surse de catalog</a>
                    <a href="{{ route('admin.settings') }}" class="btn-ghost px-0">Setări</a>
                </div>
            </x-admin.section>
        </aside>
    </div>
</div>
