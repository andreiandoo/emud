<div>
    <x-admin.page-header title="Service auto" subtitle="Directorul de ateliere, listările plătite și lead-urile pe care le-au primit.">
        <x-slot:actions>
            <a href="{{ route('admin.service-catalog') }}" class="btn-secondary">Lucrări</a>
            <a href="{{ route('admin.service-appointments') }}" class="btn-secondary">Programări</a>
            <a href="{{ route('admin.service-shops.create') }}" class="btn-primary">Service nou</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.tabs :tabs="$tabs" :current="$tab" :counts="$counts" field="tab" />

    <label class="relative mb-4 block max-w-md">
        <span class="sr-only">Caută service</span>
        <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Nume sau oraș">
    </label>

    @if($shops->isEmpty())
        <x-admin.empty title="Niciun service" hint="Adaugă primul atelier în director.">
            <a href="{{ route('admin.service-shops.create') }}" class="btn-primary">Service nou</a>
        </x-admin.empty>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Oraș</th>
                        <th class="text-right">Lucrări</th>
                        <th class="text-right">Lead-uri luna asta</th>
                        <th>Promovare</th>
                        <th>Stare</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shops as $shop)
                        @php($shopLeads = $leads[$shop->id] ?? [])
                        <tr wire:key="shop-{{ $shop->id }}">
                            <td>
                                <a href="{{ route('admin.service-shops.edit', $shop) }}" class="font-medium text-stone-900 hover:underline">{{ $shop->name }}</a>
                                @if($shop->workshop_id)
                                    <a href="{{ route('admin.workshops.show', $shop->workshop_id) }}" title="Fișă preluată din registrul național"
                                       class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-800 ring-1 ring-sky-200 hover:bg-sky-100">registru</a>
                                @endif
                                <div class="text-xs text-stone-500">{{ $shop->address ?: '—' }}</div>
                            </td>

                            <td class="text-stone-600">{{ $shop->city }}, {{ $shop->county }}</td>
                            <td class="text-right tabular-nums">{{ $shop->services_count }}</td>

                            <td class="text-right">
                                @if($shopLeads === [])
                                    <span class="text-stone-400">0</span>
                                @else
                                    <span class="font-medium tabular-nums">{{ array_sum($shopLeads) }}</span>
                                    <div class="text-xs text-stone-500">
                                        {{ collect($leadTypes)
                                            ->filter(fn ($type) => ($shopLeads[$type->value] ?? 0) > 0)
                                            ->map(fn ($type) => $type->label().' '.$shopLeads[$type->value])
                                            ->implode(' · ') }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                @php($tier = $shop->effectiveTier())
                                <x-admin.status :label="$tier->label()" :tone="$tier->isPaid() ? 'info' : 'neutral'" />
                                {{-- An expired promotion is still on the record, and an operator
                                     renewing it needs to see that it lapsed, not just that it is
                                     no longer ranking. --}}
                                @if($shop->promotion_tier->isPaid() && ! $shop->isPromoted())
                                    <div class="text-xs text-amber-700">expirată {{ $shop->promoted_until?->format('d.m.Y') }}</div>
                                @endif
                            </td>

                            <td>
                                <button type="button" wire:click="togglePublished({{ $shop->id }})">
                                    <x-admin.status :label="$shop->status === 'published' ? 'Publicat' : 'Ciornă'"
                                                    :tone="$shop->status === 'published' ? 'positive' : 'neutral'" />
                                </button>
                            </td>

                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action href="{{ route('admin.service-shops.edit', $shop) }}">Editează</x-admin.row-action>
                                    @if($shop->status === 'published')
                                        <x-admin.row-action href="{{ $shop->url() }}">Vezi public</x-admin.row-action>
                                    @endif
                                    <x-admin.row-action href="{{ route('admin.service-appointments', ['shop' => $shop->id, 'status' => '']) }}">Programări</x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $shops->links() }}</div>
    @endif
</div>
