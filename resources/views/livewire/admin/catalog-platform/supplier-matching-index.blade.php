<div>
    <x-admin.page-header title="Potrivire furnizor → piesă canonică"
                         subtitle="GTIN și brand+MPN mapează automat. Referințele OE contează doar când brandul coincide — un echivalent de alt producător nu e același articol." />

    @if(session('status'))
        <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

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
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
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
            @php($reason = $product->catalog_mapping_reason ?? [])
            <div class="card-padded" wire:key="supplier-product-{{ $product->id }}">
                <div class="grid gap-6 xl:grid-cols-[1.2fr_2fr]">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-stone-500">
                            {{ $product->supplier?->code }} · {{ $statuses[$product->catalog_mapping_status] ?? $product->catalog_mapping_status }}
                        </div>
                        <div class="mt-1 font-medium text-stone-900">{{ $product->name }}</div>

                        <x-admin.definition class="mt-3" :rows="[
                            'SKU' => $product->supplier_sku,
                            'EAN' => $product->ean,
                            'Brand' => $product->raw_brand,
                            'MPN' => $product->manufacturer_part_number,
                        ]" />

                        {{-- Every identifier the feed carried, not just the two with columns:
                             an operator deciding on an OE-based candidate needs to see the OE. --}}
                        @php($extraIdentifiers = $product->identifiers->reject(fn ($identifier) => in_array($identifier->type->value, ['GTIN', 'MPN', 'SUPPLIER_SKU'], true)))
                        @if($extraIdentifiers->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($extraIdentifiers as $identifier)
                                    <span class="rounded border border-stone-200 bg-stone-50 px-2 py-0.5 font-mono text-[11px] text-stone-700" title="{{ $identifier->type->label() }}">
                                        <span class="text-stone-400">{{ $identifier->type->label() }}</span>
                                        {{ $identifier->brand ? $identifier->brand.' ' : '' }}{{ $identifier->value }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if(filled($reason['invalid_gtin'] ?? null))
                            <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                                EAN-ul <strong class="font-mono">{{ $reason['invalid_gtin'] }}</strong> nu e un cod de bare valid (placeholder sau cifră de control greșită), așa că nu a fost folosit la potrivire.
                            </p>
                        @endif

                        @if($product->catalog_mapping_status === 'conflict')
                            <p class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-xs text-red-900">
                                Două piese sunt revendicate cu încredere maximă — de obicei EAN-ul arată spre una și brand+MPN spre alta. Unul dintre identificatorii furnizorului e greșit; confirmă manual piesa corectă.
                            </p>
                        @endif

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
                                @php($candidateBrand = $candidate->catalogPart?->brand)
                                @php($canAlias = filled($product->raw_brand) && $candidateBrand && \Illuminate\Support\Str::slug($product->raw_brand) !== $candidateBrand->slug)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50 p-3"
                                     wire:key="candidate-{{ $candidate->id }}">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.catalog-platform.parts.show', $candidate->catalogPart) }}" class="font-medium text-stone-900 hover:underline">
                                            {{ $candidateBrand?->name }} {{ $candidate->catalogPart?->mpn_raw }}
                                        </a>
                                        {{-- The reasons are shown, not just the score: an operator
                                             confirming a match needs to know what matched. --}}
                                        <div class="text-xs text-stone-500">
                                            {{ $candidate->score }}% · {{ implode(', ', $candidate->reasons ?? []) }}
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap gap-2">
                                        <button type="button" wire:click="confirm({{ $product->id }}, {{ $candidate->catalog_part_id }})" class="btn-primary">Confirmă</button>
                                        @if($canAlias)
                                            <button type="button" wire:click="aliasBrand({{ $candidate->id }})" class="btn-ghost"
                                                    title="Toate articolele acestui furnizor scrise „{{ $product->raw_brand }}” vor fi tratate ca {{ $candidateBrand->name }}.">
                                                „{{ $product->raw_brand }}” = {{ $candidateBrand->name }}
                                            </button>
                                        @endif
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
