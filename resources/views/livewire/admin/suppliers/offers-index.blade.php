<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight">Economia ofertelor</h1>
        <p class="text-stone-500">Cost aterizat și contribuție per ofertă. Prețul unitar e doar unul dintre ingrediente — pe piese voluminoase rareori e cel decisiv.</p>
    </div>

    <div class="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="space-y-1"><span class="text-xs text-stone-500">Caută</span><input wire:model.live.debounce.300ms="search" placeholder="Denumire, SKU sau MPN"></label>
        <label class="space-y-1"><span class="text-xs text-stone-500">Furnizor</span><select wire:model.live="supplier"><option value="">Toți</option>@foreach($suppliers as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label class="space-y-1"><span class="text-xs text-stone-500">Clasă transport</span><select wire:model.live="shippingClass"><option value="">Toate</option>@foreach($shippingClasses as $class)<option value="{{ $class->value }}">{{ $class->label() }}</option>@endforeach</select></label>
        <label class="flex items-center gap-2 self-end rounded border p-3 text-sm"><input type="checkbox" wire:model.live="onlyIncomplete"><span>Doar cu date lipsă</span></label>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-50 text-xs uppercase text-stone-500">
                    <tr>
                        <th class="p-3">Produs</th><th class="p-3">Furnizor</th>
                        <th class="p-3 text-right">Cost furnizor</th><th class="p-3 text-right">Cost în bază</th>
                        <th class="p-3 text-right">Taxe + transport</th><th class="p-3 text-right">Cost aterizat</th>
                        <th class="p-3 text-right">Preț</th><th class="p-3 text-right">Contribuție</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                @forelse($rows as $row)
                    @php($offer = $row['offer'])
                    @php($economics = $row['economics'])
                    @php($landed = $economics['landed_cost_breakdown'] ?? null)
                    <tr class="align-top">
                        <td class="p-3">
                            <div class="font-medium">{{ $offer->supplierProduct->name }}</div>
                            <div class="text-xs text-stone-500">
                                {{ $offer->supplierProduct->supplier_sku ?? $offer->supplierProduct->external_id }}
                                @if($offer->shipping_class)· {{ $offer->shipping_class->label() }}@endif
                                @if($offer->source_seller_name)· vânzător: {{ $offer->source_seller_name }}@endif
                            </div>
                        </td>
                        <td class="p-3 text-xs text-stone-600">
                            {{ $offer->supplierProduct->supplier->code }}
                            @if($offer->warehouse)<div class="text-stone-400">{{ $offer->warehouse->code }}</div>@endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">{{ $offer->cost_price !== null ? number_format((float) $offer->cost_price, 2).' '.$offer->currency : '—' }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @if($offer->base_cost_net !== null)
                                {{ number_format((float) $offer->base_cost_net, 2) }} {{ $offer->base_currency }}
                                <div class="text-[10px] text-stone-400">curs {{ rtrim(rtrim((string) $offer->fx_rate, '0'), '.') }} · {{ $offer->fx_rate_at?->format('d.m.Y') }}</div>
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap text-xs text-stone-600">
                            @if($landed)
                                dropship {{ number_format($landed['dropship_fee'], 2) }}<br>
                                manipulare {{ number_format($landed['handling_fee'], 2) }}<br>
                                transport {{ number_format($landed['freight'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="p-3 text-right font-medium whitespace-nowrap">
                            @if($landed)
                                {{ number_format($landed['total'], 2) }} {{ $landed['currency'] }}
                                @unless($landed['complete'])
                                    <div class="text-[10px] font-normal text-amber-700">incomplet: {{ implode(', ', $landed['missing']) }}</div>
                                @endunless
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">{{ $row['selling_price'] !== null ? number_format($row['selling_price'], 2) : '—' }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            @if($economics)
                                <span @class([
                                    'font-bold',
                                    'text-emerald-700' => $economics['contribution'] > 0 && ($economics['contribution_percent'] ?? 0) >= 20,
                                    'text-amber-700' => $economics['contribution'] > 0 && ($economics['contribution_percent'] ?? 0) < 20,
                                    'text-red-700' => $economics['contribution'] <= 0,
                                ])>{{ number_format($economics['contribution'], 2) }}</span>
                                <div class="text-[10px] text-stone-500">{{ $economics['contribution_percent'] !== null ? $economics['contribution_percent'].'%' : '' }} · brut {{ $economics['gross_margin_percent'] !== null ? $economics['gross_margin_percent'].'%' : '—' }}</div>
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-8 text-center text-stone-500">Nicio ofertă pentru filtrele curente.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t p-4">{{ $offers->links() }}</div>
    </div>

    <p class="mt-4 text-xs text-stone-500">Contribuția scade comisionul de plată, rezerva de retur și cea de garanție din venitul net. Ratele sunt setări de planificare în <code>config/emud.php</code> și trebuie înlocuite cu valori măsurate imediat ce există istoric real.</p>
</div>
