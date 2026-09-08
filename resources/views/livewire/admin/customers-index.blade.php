<div>
    <x-admin.page-header title="Clienți" subtitle="Conturi de client, garaj și comenzi." />

    <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
        <div>
            <input wire:model.live.debounce.400ms="search" placeholder="Caută după nume sau email" class="mb-4 max-w-md">

            @if($customers->isEmpty())
                <x-admin.empty title="Niciun client" hint="Conturile create din magazin apar aici." />
            @else
                <div class="card overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Mașini</th>
                                <th>Favorite</th>
                                <th>Marketing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                <tr wire:click="select({{ $customer->id }})" @class([
                                    'cursor-pointer',
                                    'bg-stone-100' => $selected?->id === $customer->id,
                                ])>
                                    <td class="font-medium">{{ $customer->name }}</td>
                                    <td class="text-stone-600">{{ $customer->email }}</td>
                                    <td>{{ $customer->vehicles_count }}</td>
                                    <td>{{ $customer->wishlist_items_count }}</td>
                                    <td>
                                        @if($customer->marketing_consent_at)
                                            <span class="pill-positive">acordat</span>
                                        @else
                                            <span class="pill-neutral">fără</span>
                                        @endif
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
                <div class="card-padded space-y-5">
                    <div>
                        <h2 class="text-base font-semibold">{{ $selected->name }}</h2>
                        <p class="text-sm text-stone-600">{{ $selected->email }}</p>
                        @if($selected->phone)<p class="text-sm text-stone-600">{{ $selected->phone }}</p>@endif
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-stone-500">Garaj</h3>
                        @forelse($selected->vehicles as $vehicle)
                            <p class="text-sm">
                                {{ $vehicle->make?->name }} {{ $vehicle->model?->name }} · {{ $vehicle->year }}
                                @if($vehicle->is_primary)<span class="pill-positive ml-1">principală</span>@endif
                            </p>
                        @empty
                            <p class="text-sm text-stone-500">Nicio mașină salvată.</p>
                        @endforelse
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-stone-500">Ultimele comenzi</h3>
                        @forelse($orders as $order)
                            <div class="flex items-baseline justify-between gap-2 border-b border-stone-100 py-1.5 text-sm last:border-b-0">
                                <span class="font-medium">{{ $order->number }}</span>
                                <span class="text-stone-600">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-stone-500">Nicio comandă.</p>
                        @endforelse
                    </div>

                    {{-- Stated rather than assumed: consent is evidence of a decision the
                         customer made, and its absence is just as meaningful. --}}
                    <div class="border-t border-stone-100 pt-4 text-xs text-stone-500">
                        Consimțământ marketing: {{ $selected->marketing_consent_at?->format('d.m.Y') ?? 'neacordat' }}
                    </div>
                </div>
            @endif
        </aside>
    </div>
</div>
