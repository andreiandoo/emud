<div>
    <x-admin.page-header title="Prețuri de aprobat"
                         subtitle="Mișcările de preț prea mari ca să intre singure pe site, și jurnalul celor care au intrat. Aici se oprește o eroare de feed care înjumătățește un cost înainte să înjumătățească un preț." />

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach($statuses as $item)
            <button type="button" wire:click="$set('status', '{{ $item->value }}')"
                @class([
                    'rounded-lg border px-3 py-1.5 text-xs',
                    'border-stone-900 bg-stone-900 text-white' => $status === $item->value,
                    'border-stone-200 bg-white text-stone-600 hover:bg-stone-50' => $status !== $item->value,
                ])>{{ $item->label() }} <span class="font-bold">{{ $counts[$item->value] ?? 0 }}</span></button>
        @endforeach
        <button type="button" wire:click="$set('status', '')" @class(['rounded-lg border px-3 py-1.5 text-xs', 'border-stone-900 bg-stone-900 text-white' => $status === '', 'border-stone-200 bg-white text-stone-600' => $status !== ''])>Toate</button>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-50 text-xs uppercase text-stone-500">
                    <tr>
                        <th class="p-3">Produs</th><th class="p-3 text-right">Preț actual</th><th class="p-3 text-right">Preț propus</th>
                        <th class="p-3">De ce</th><th class="p-3 text-right">Contribuție</th><th class="p-3">Status</th><th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                @forelse($changes as $change)
                    @php($decision = $change->decision ?? [])
                    @php($move = $change->changePercent())
                    <tr class="align-top" wire:key="change-{{ $change->id }}">
                        <td class="p-3">
                            <a href="{{ route('admin.products.edit', $change->product_id) }}" class="font-medium text-stone-900 hover:underline">{{ $change->product?->name ?? 'Produs #'.$change->product_id }}</a>
                            <div class="text-xs text-stone-500">{{ $change->variant?->sku }} · {{ $change->created_at?->format('d.m.Y H:i') }}</div>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">{{ $change->old_price !== null ? \App\Support\Money::of($change->old_price, $change->currency)->format() : '—' }}</td>
                        <td class="p-3 text-right whitespace-nowrap font-semibold">
                            {{ \App\Support\Money::of($change->new_price, $change->currency)->format() }}
                            @if($move !== null)
                                <div @class(['text-xs font-normal', 'text-red-700' => $move < 0, 'text-emerald-700' => $move > 0])>{{ $move > 0 ? '+' : '' }}{{ $move }}%</div>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-stone-600">
                            <div>Furnizor {{ $decision['supplier_code'] ?? '—' }}, cost aterizat {{ isset($decision['unit_landed_cost']) ? number_format((float) $decision['unit_landed_cost'], 2) : '—' }}</div>
                            <div>
                                Regula: {{ $decision['policy']['scope'] ?? '—' }}, marjă {{ $decision['policy']['target_margin_percent'] ?? '—' }}%
                                @if(($decision['binding'] ?? null) === 'minimum_contribution') · <strong>ridicat la contribuția minimă</strong>
                                @elseif(($decision['binding'] ?? null) === 'map') · <strong>ridicat la MAP</strong>
                                @endif
                            </div>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap text-xs">{{ isset($decision['contribution_percent']) ? $decision['contribution_percent'].'%' : '—' }}</td>
                        <td class="p-3"><span class="rounded bg-stone-100 px-2 py-0.5 text-[11px] text-stone-700">{{ $change->status->label() }}</span></td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @if($change->status === \App\Enums\PriceChangeStatus::Pending)
                                <button type="button" wire:click="approve({{ $change->id }})" class="btn-primary">Aprobă</button>
                                <button type="button" wire:click="reject({{ $change->id }})" class="btn-ghost">Respinge</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-stone-500">Nimic de afișat pentru acest filtru.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t p-4">{{ $changes->links() }}</div>
    </div>
</div>
