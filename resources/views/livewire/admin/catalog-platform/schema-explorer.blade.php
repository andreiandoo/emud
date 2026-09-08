<div>
    <x-admin.page-header title="Explorator de schemă"
                         subtitle="Tabele, coloane, indexuri și chei străine, fără să deschizi un client de bază de date.">
        <x-slot:actions>
            <span class="text-sm text-stone-500">{{ $tableCount }} tabele</span>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-8 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <aside>
            <label class="relative mb-3 block">
                <span class="sr-only">Filtrează tabelele</span>
                <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.250ms="search" class="pl-9" placeholder="Filtrează tabelele...">
            </label>

            <div class="max-h-[70vh] space-y-1 overflow-y-auto pr-1">
                @forelse($tables as $table)
                    <button type="button" wire:key="table-{{ $table['name'] }}" wire:click="selectTable('{{ $table['name'] }}')" @class([
                        'w-full rounded-lg px-3 py-2 text-left text-sm transition',
                        'bg-stone-900 text-white' => $selected === $table['name'],
                        'hover:bg-stone-100' => $selected !== $table['name'],
                    ])>
                        <span class="block break-all font-mono">{{ $table['name'] }}</span>
                        @if($table['schema'])
                            <span class="text-xs text-stone-400">{{ $table['schema'] }}</span>
                        @endif
                    </button>
                @empty
                    <p class="p-3 text-sm text-stone-500">Niciun tabel nu se potrivește cu filtrul.</p>
                @endforelse
            </div>
        </aside>

        <section class="min-w-0 space-y-8">
            @if($introspectionError)
                <p class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ $introspectionError }}</p>
            @endif

            @if($selected)
                <h2 class="break-all font-mono text-lg font-semibold text-stone-900">{{ $selected }}</h2>

                <x-admin.section title="Coloane">
                    <x-slot:aside>{{ count($columns) }}</x-slot:aside>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th>Nume</th><th>Tip</th><th>Nullable</th><th>Implicit</th><th>Auto</th><th>Comentariu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($columns as $column)
                                    <tr wire:key="column-{{ $selected }}-{{ $column['name'] ?? $loop->index }}">
                                        <td class="font-mono font-medium text-stone-900">{{ $column['name'] ?? '' }}</td>
                                        <td class="font-mono text-xs text-stone-600">{{ $column['type'] ?? $column['type_name'] ?? '' }}</td>
                                        <td class="text-stone-600">{{ ($column['nullable'] ?? false) ? 'da' : 'nu' }}</td>
                                        <td class="max-w-xs break-all font-mono text-xs text-stone-600">{{ is_scalar($column['default'] ?? null) ? $column['default'] : '' }}</td>
                                        <td class="text-stone-600">{{ ($column['auto_increment'] ?? false) ? 'da' : '' }}</td>
                                        <td class="text-stone-500">{{ $column['comment'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.section>

                <x-admin.section title="Indexuri">
                    <x-slot:aside>{{ count($indexes) }}</x-slot:aside>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr><th>Nume</th><th>Coloane</th><th>Tip</th><th>Unic</th><th>Primar</th></tr>
                            </thead>
                            <tbody>
                                @foreach($indexes as $index)
                                    <tr wire:key="index-{{ $selected }}-{{ $index['name'] ?? $loop->index }}">
                                        <td class="font-mono text-xs text-stone-900">{{ $index['name'] ?? '' }}</td>
                                        <td class="font-mono text-xs text-stone-600">{{ implode(', ', $index['columns'] ?? []) }}</td>
                                        <td class="text-stone-600">{{ $index['type'] ?? '' }}</td>
                                        <td class="text-stone-600">{{ ($index['unique'] ?? false) ? 'da' : 'nu' }}</td>
                                        <td class="text-stone-600">{{ ($index['primary'] ?? false) ? 'da' : 'nu' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.section>

                <x-admin.section title="Chei străine">
                    <x-slot:aside>{{ count($foreignKeys) }}</x-slot:aside>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr><th>Nume</th><th>Coloane locale</th><th>Referință</th><th>La update</th><th>La delete</th></tr>
                            </thead>
                            <tbody>
                                @foreach($foreignKeys as $key)
                                    <tr wire:key="fk-{{ $selected }}-{{ $key['name'] ?? $loop->index }}">
                                        <td class="font-mono text-xs text-stone-900">{{ $key['name'] ?? '' }}</td>
                                        <td class="font-mono text-xs text-stone-600">{{ implode(', ', $key['columns'] ?? []) }}</td>
                                        <td class="font-mono text-xs text-stone-600">
                                            {{ ($key['foreign_schema'] ?? '') ? $key['foreign_schema'].'.' : '' }}{{ $key['foreign_table'] ?? '' }}
                                            ({{ implode(', ', $key['foreign_columns'] ?? []) }})
                                        </td>
                                        <td class="text-stone-600">{{ $key['on_update'] ?? '' }}</td>
                                        <td class="text-stone-600">{{ $key['on_delete'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-admin.section>
            @else
                <x-admin.empty title="Niciun tabel selectat" hint="Alege un tabel din lista din stânga." />
            @endif
        </section>
    </div>
</div>
