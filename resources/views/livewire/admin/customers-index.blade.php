<div>
    <x-admin.page-header title="Clienți" subtitle="Conturi de client, garaj și comenzi." />

    <div class="grid gap-8 lg:grid-cols-[1fr_24rem]">
        <div>
            <label class="relative mb-4 block max-w-md">
                <span class="sr-only">Caută clienți</span>
                <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.400ms="search" class="pl-9" placeholder="Caută după nume sau email">
            </label>

            @if($customers->isEmpty())
                <x-admin.empty title="Niciun client" hint="Conturile create din magazin apar aici." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th class="text-right">Mașini</th>
                                <th class="text-right">Favorite</th>
                                <th>Marketing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                <tr wire:key="customer-{{ $customer->id }}" wire:click="select({{ $customer->id }})" @class([
                                    'cursor-pointer',
                                    'bg-stone-100' => $selected?->id === $customer->id,
                                ])>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <x-admin.avatar :name="$customer->name" size="sm" />
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-stone-900">{{ $customer->name }}</div>
                                                <div class="truncate text-xs text-stone-500">{{ $customer->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $customer->vehicles_count }}</td>
                                    <td class="text-right tabular-nums">{{ $customer->wishlist_items_count }}</td>
                                    <td>
                                        <x-admin.status :label="$customer->marketing_consent_at ? 'Acordat' : 'Fără'"
                                                        :tone="$customer->marketing_consent_at ? 'positive' : 'neutral'" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $customers->links() }}</div>
            @endif
        </div>

        <aside class="h-fit">
            @if($selected === null)
                <x-admin.empty title="Selectează un client" hint="Alege un rând ca să vezi garajul și comenzile." />
            @else
                <x-admin.panel :title="$selected->name" :subtitle="$selected->email">
                    <x-slot:actions>
                        <a href="mailto:{{ $selected->email }}" class="btn-secondary">Scrie-i</a>
                    </x-slot:actions>

                    <x-admin.definition :rows="[
                        'Telefon' => $selected->phone,
                        'Cont creat' => $selected->created_at?->format('d.m.Y'),
                        'Mașini' => $selected->vehicles->count(),
                        'Marketing' => $selected->marketing_consent_at?->format('d.m.Y') ?? 'neacordat',
                    ]" />

                    <x-admin.section title="Garaj">
                        @forelse($selected->vehicles as $vehicle)
                            <p class="flex items-center gap-2 text-sm text-stone-700">
                                <x-admin.icon name="car" class="h-4 w-4 text-stone-400" />
                                {{ $vehicle->make?->name }} {{ $vehicle->model?->name }} · {{ $vehicle->year }}
                                @if($vehicle->is_primary)<span class="pill-positive">principală</span>@endif
                            </p>
                        @empty
                            <p class="text-sm text-stone-500">Nicio mașină salvată.</p>
                        @endforelse
                    </x-admin.section>

                    <x-admin.section title="Ultimele comenzi">
                        @forelse($orders as $order)
                            <div class="flex items-baseline justify-between gap-2 border-b border-stone-100 py-1.5 text-sm last:border-b-0">
                                <a href="{{ route('admin.orders.edit', $order) }}" class="font-medium text-stone-900 hover:underline">{{ $order->number }}</a>
                                <span class="tabular-nums text-stone-600">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-stone-500">Nicio comandă.</p>
                        @endforelse
                    </x-admin.section>
                </x-admin.panel>
            @endif
        </aside>
    </div>
</div>
