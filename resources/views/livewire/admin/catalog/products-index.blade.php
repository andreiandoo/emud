<div>
    <x-admin.page-header title="Produse" subtitle="Catalogul canonic, separat de ofertele furnizorilor.">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="btn-primary">Produs nou</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <x-admin.stat :value="number_format($totals['count'], 0, ',', '.')" label="Produse în filtrul curent" />
        <x-admin.stat :value="number_format($totals['withoutOffers'], 0, ',', '.')" label="Fără nicio ofertă de furnizor"
                      :tone="$totals['withoutOffers'] > 0 ? 'warning' : 'neutral'" />
        <x-admin.stat :value="number_format($totals['withoutMedia'], 0, ',', '.')" label="Fără imagini"
                      :tone="$totals['withoutMedia'] > 0 ? 'warning' : 'neutral'" />
    </div>

    <x-admin.tabs :tabs="$tabs" :current="$status" :counts="$counts" field="status" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <label class="relative w-full sm:w-80">
            <span class="sr-only">Caută produse</span>
            <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
            <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Nume, SKU sau cod producător">
        </label>

        <label class="w-full sm:w-64">
            <span class="sr-only">Categorie</span>
            <select wire:model.live="category">
                <option value="">Toate categoriile</option>
                @foreach($categories as $item)
                    <option value="{{ $item->id }}">{{ str_repeat('— ', $item->depth) }}{{ $item->name }}</option>
                @endforeach
            </select>
        </label>

        @if($search !== '' || $status !== '' || $category !== '')
            <button type="button" wire:click="resetFilters" class="btn-ghost">Golește filtrele</button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr>
                    <th class="w-10">
                        <input type="checkbox" aria-label="Selectează pagina"
                               @checked(count($selected) > 0 && count($selected) >= $products->count())
                               wire:click="{{ count($selected) > 0 ? 'clearSelection' : 'selectPage' }}">
                    </th>
                    <th>Produs</th>
                    <th>Brand</th>
                    <th class="text-right">Variante</th>
                    <th class="text-right">Oferte</th>
                    <th>Status</th>
                    <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php($thumbnail = $product->media->firstWhere('type', 'image'))
                    <tr wire:key="product-{{ $product->id }}" @class(['bg-stone-50' => in_array($product->id, $selected, true)])>
                        <td>
                            <input type="checkbox" value="{{ $product->id }}" wire:model.live="selected"
                                   aria-label="Selectează {{ $product->name }}">
                        </td>

                        <td>
                            <div class="flex items-center gap-3">
                                {{-- A fixed grey square where there is no image, so the column keeps its
                                     rhythm instead of the rows jumping between two heights. --}}
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-stone-100">
                                    @if($thumbnail)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk($thumbnail->disk)->url($thumbnail->path) }}"
                                             alt="{{ $thumbnail->alt_text ?: $product->name }}" class="h-full w-full object-cover">
                                    @else
                                        <x-admin.icon name="image" class="h-4 w-4 text-stone-400" />
                                    @endif
                                </span>

                                <div class="min-w-0">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="block truncate font-medium text-stone-900 hover:underline">{{ $product->name }}</a>
                                    <div class="truncate text-xs text-stone-500">{{ $product->sku ?: $product->manufacturer_part_number ?: 'fără cod' }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="text-stone-600">{{ $product->brand?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $product->variants->count() }}</td>
                        <td class="text-right tabular-nums">
                            @if($product->supplier_products_count === 0)
                                <span class="text-amber-700">0</span>
                            @else
                                {{ $product->supplier_products_count }}
                            @endif
                        </td>

                        <td>
                            <x-admin.status :label="$tabs[$product->status->value] ?? $product->status->value"
                                            :tone="match ($product->status->value) {
                                                'active' => 'positive',
                                                'review' => 'warning',
                                                'archived' => 'neutral',
                                                default => 'info',
                                            }" />
                        </td>

                        <td>
                            <x-admin.row-actions>
                                <x-admin.row-action href="{{ route('admin.products.edit', $product) }}">Editează</x-admin.row-action>
                                @foreach(['active' => 'Publică', 'review' => 'Trimite la verificare', 'draft' => 'Treci în ciornă', 'archived' => 'Arhivează'] as $value => $label)
                                    @if($product->status->value !== $value)
                                        <x-admin.row-action wire:click="setStatus({{ $product->id }}, '{{ $value }}')">{{ $label }}</x-admin.row-action>
                                    @endif
                                @endforeach
                            </x-admin.row-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-0">
                            <x-admin.empty title="Niciun produs" hint="Nimic nu se potrivește cu filtrele curente." class="border-0">
                                <a href="{{ route('admin.products.create') }}" class="btn-primary">Adaugă un produs</a>
                            </x-admin.empty>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    <x-admin.bulk-bar :count="count($selected)" clear="clearSelection">
        <x-admin.bulk-action wire:click="bulkStatus('active')">Publică</x-admin.bulk-action>
        <x-admin.bulk-action wire:click="bulkStatus('review')">La verificare</x-admin.bulk-action>
        <x-admin.bulk-action wire:click="bulkStatus('archived')">Arhivează</x-admin.bulk-action>
    </x-admin.bulk-bar>
</div>
