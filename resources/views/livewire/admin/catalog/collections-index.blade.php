<div>
    <x-admin.page-header title="Colecții"
                         subtitle="Catalogul văzut pe model de mașină. Fiecare colecție are pagina ei în magazin.">
        <x-slot:actions>
            <a href="{{ route('admin.collections.create') }}" class="btn-primary">Colecție nouă</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($flash !== '')
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-900">{{ $flash }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Caută marca sau modelul…" class="max-w-xs">
    </div>

    <x-admin.tabs :tabs="$tabs" :current="$tab" :counts="$counts" />

    @if($collections->isEmpty())
        <x-admin.empty title="Nicio colecție"
                       hint="Rulează seed-ul pe modelele din baza de date sau adaugă una manual.">
            <a href="{{ route('admin.collections.create') }}" class="btn-primary">Colecție nouă</a>
        </x-admin.empty>
    @else
        <div class="table-flat">
            <table>
                <thead>
                    <tr>
                        <th class="w-14"><span class="sr-only">Imagine</span></th>
                        <th>Colecție</th>
                        <th>Marcă</th>
                        <th>Ani</th>
                        <th class="text-right">Produse</th>
                        <th class="text-right">Recenzii</th>
                        <th>În carusel</th>
                        <th>Stare</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($collections as $collection)
                        <tr wire:key="collection-{{ $collection->id }}">
                            <td>
                                @if($collection->squareImageUrl())
                                    <img src="{{ $collection->squareImageUrl() }}" alt=""
                                         class="h-10 w-10 rounded-lg object-cover">
                                @else
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-stone-100 text-stone-400">
                                        <x-admin.icon name="car" />
                                    </span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.collections.edit', $collection) }}" class="font-medium text-stone-900 hover:underline">
                                    {{ $collection->name }}
                                </a>
                                <span class="block text-xs text-stone-400">/colectii/{{ $collection->slug }}</span>
                            </td>
                            <td class="text-stone-600">{{ $collection->make?->name ?? '—' }}</td>
                            <td class="text-stone-600">{{ $collection->yearRange() ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $collection->products_count }}</td>
                            <td class="text-right tabular-nums">{{ $collection->reviews_count }}</td>
                            <td>
                                <button type="button" wire:click="toggleFeatured({{ $collection->id }})"
                                        class="{{ $collection->is_featured ? 'pill-positive' : 'pill-neutral' }}">
                                    {{ $collection->is_featured ? 'Da' : 'Nu' }}
                                </button>
                            </td>
                            <td>
                                <x-admin.status :label="$collection->is_active ? 'Publicată' : 'Ascunsă'"
                                                :tone="$collection->is_active ? 'positive' : 'neutral'" />
                            </td>
                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action href="{{ route('admin.collections.edit', $collection) }}">Editează</x-admin.row-action>
                                    <x-admin.row-action href="{{ route('storefront.collection', $collection->slug) }}" target="_blank">Vezi în magazin</x-admin.row-action>
                                    <x-admin.row-action wire:click="toggleActive({{ $collection->id }})">
                                        {{ $collection->is_active ? 'Ascunde' : 'Publică' }}
                                    </x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $collections->links() }}</div>
    @endif
</div>
