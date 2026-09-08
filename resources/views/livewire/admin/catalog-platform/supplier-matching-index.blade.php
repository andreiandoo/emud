<div>
    <x-admin.page-header title="Potrivire furnizor → piesă canonică"
                         subtitle="Mapează SKU-urile comerciale la piesa tehnică canonică. EAN și brand+MPN au prioritate." />

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <label class="block">
            <span class="field-label">Furnizor</span>
            <select wire:model.live="supplier">
                <option value="">Toți furnizorii</option>
                @foreach($suppliers as $item)<option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }}</option>@endforeach
            </select>
        </label>

        <label class="block">
            <span class="field-label">Status de mapare</span>
            <select wire:model.live="status">
                @foreach(['candidate', 'unmatched', 'unmapped', 'mapped_auto', 'mapped_manual'] as $value)
                    <option value="{{ $value }}">{{ $value }}</option>
                @endforeach
            </select>
        </label>

        <label class="relative block">
            <span class="field-label">Caută</span>
            <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-[2.1rem] h-4 w-4 text-stone-400" />
            <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="SKU, EAN, MPN, nume...">
        </label>
    </div>

    <div class="space-y-4">
        @forelse($products as $product)
            <div class="card-padded" wire:key="supplier-product-{{ $product->id }}">
                <div class="grid gap-6 xl:grid-cols-[1.2fr_2fr]">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-stone-500">
                            {{ $product->supplier?->code }} · {{ $product->catalog_mapping_status }}
                        </div>
                        <div class="mt-1 font-medium text-stone-900">{{ $product->name }}</div>

                        <x-admin.definition class="mt-3" :rows="[
                            'SKU' => $product->supplier_sku,
                            'EAN' => $product->ean,
                            'Brand' => $product->raw_brand,
                            'MPN' => $product->manufacturer_part_number,
                        ]" />

                        @if($product->catalogPart)
                            <p class="mt-3 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">
                                Mapat la <strong>{{ $product->catalogPart?->brand?->name }} {{ $product->catalogPart?->mpn_raw }}</strong>
                                · {{ $product->mapping_confidence }}%
                            </p>
                        @endif
                    </div>

                    <div>
                        <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-stone-500">Candidați</h3>

                        <div class="space-y-2">
                            @forelse($product->catalogCandidates as $candidate)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50 p-3"
                                     wire:key="candidate-{{ $candidate->id }}">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.catalog-platform.parts.show', $candidate->catalogPart) }}" class="font-medium text-stone-900 hover:underline">
                                            {{ $candidate->catalogPart?->brand?->name }} {{ $candidate->catalogPart?->mpn_raw }}
                                        </a>
                                        {{-- The reasons are shown, not just the score: an operator
                                             confirming a match needs to know what matched. --}}
                                        <div class="text-xs text-stone-500">
                                            {{ $candidate->score }}% · {{ implode(', ', $candidate->reasons ?? []) }}
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" wire:click="confirm({{ $product->id }}, {{ $candidate->catalog_part_id }})" class="btn-primary">Confirmă</button>
                                        <button type="button" wire:click="reject({{ $candidate->id }})" class="btn-ghost">Respinge</button>
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-stone-300 p-4 text-sm text-stone-500">Niciun candidat.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <x-admin.empty title="Niciun produs de furnizor" hint="Nimic nu se potrivește cu filtrele curente." />
        @endforelse
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</div>
