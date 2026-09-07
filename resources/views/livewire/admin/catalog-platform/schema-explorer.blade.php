<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-sm text-stone-500">Catalog platform</p>
            <h1 class="text-2xl font-bold">Database schema explorer</h1>
            <p class="mt-1 text-sm text-stone-500">Inspect all application tables, columns, indexes and foreign keys without opening a database client.</p>
        </div>
        <div class="text-sm text-stone-500">{{ $tableCount }} tables</div>
    </div>

    <div class="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <aside class="rounded-xl border bg-white p-4">
            <input wire:model.live.debounce.250ms="search" placeholder="Filter tables..." class="mb-3 w-full rounded-lg border px-3 py-2 text-sm">
            <div class="max-h-[70vh] space-y-1 overflow-y-auto pr-1">
                @forelse($tables as $table)
                    <button type="button" wire:click="selectTable('{{ $table['name'] }}')" @class([
                        'w-full rounded-lg px-3 py-2 text-left text-sm transition',
                        'bg-lime-100 font-semibold text-lime-950' => $selected === $table['name'],
                        'hover:bg-stone-100' => $selected !== $table['name'],
                    ])>
                        <span class="block break-all font-mono">{{ $table['name'] }}</span>
                        @if($table['schema'])<span class="text-xs text-stone-400">{{ $table['schema'] }}</span>@endif
                    </button>
                @empty
                    <p class="p-3 text-sm text-stone-500">No tables match this filter.</p>
                @endforelse
            </div>
        </aside>

        <section class="min-w-0 space-y-5">
            @if($introspectionError)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $introspectionError }}</div>
            @endif

            @if($selected)
                <div class="rounded-xl border bg-white p-5">
                    <p class="text-xs uppercase tracking-wide text-stone-400">Selected table</p>
                    <h2 class="mt-1 break-all font-mono text-xl font-bold">{{ $selected }}</h2>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white">
                    <div class="border-b px-5 py-3 font-semibold">Columns · {{ count($columns) }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Nullable</th><th class="px-4 py-3">Default</th><th class="px-4 py-3">Auto</th><th class="px-4 py-3">Comment</th></tr></thead>
                            <tbody class="divide-y">
                            @foreach($columns as $column)
                                <tr><td class="px-4 py-3 font-mono font-medium">{{ $column['name'] ?? '' }}</td><td class="px-4 py-3 font-mono text-xs">{{ $column['type'] ?? $column['type_name'] ?? '' }}</td><td class="px-4 py-3">{{ ($column['nullable'] ?? false) ? 'yes' : 'no' }}</td><td class="max-w-xs break-all px-4 py-3 font-mono text-xs">{{ is_scalar($column['default'] ?? null) ? $column['default'] : '' }}</td><td class="px-4 py-3">{{ ($column['auto_increment'] ?? false) ? 'yes' : '' }}</td><td class="px-4 py-3 text-stone-500">{{ $column['comment'] ?? '' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white">
                    <div class="border-b px-5 py-3 font-semibold">Indexes · {{ count($indexes) }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Columns</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Unique</th><th class="px-4 py-3">Primary</th></tr></thead>
                            <tbody class="divide-y">
                            @foreach($indexes as $index)
                                <tr><td class="px-4 py-3 font-mono text-xs">{{ $index['name'] ?? '' }}</td><td class="px-4 py-3 font-mono text-xs">{{ implode(', ', $index['columns'] ?? []) }}</td><td class="px-4 py-3">{{ $index['type'] ?? '' }}</td><td class="px-4 py-3">{{ ($index['unique'] ?? false) ? 'yes' : 'no' }}</td><td class="px-4 py-3">{{ ($index['primary'] ?? false) ? 'yes' : 'no' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border bg-white">
                    <div class="border-b px-5 py-3 font-semibold">Foreign keys · {{ count($foreignKeys) }}</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Local columns</th><th class="px-4 py-3">References</th><th class="px-4 py-3">On update</th><th class="px-4 py-3">On delete</th></tr></thead>
                            <tbody class="divide-y">
                            @foreach($foreignKeys as $key)
                                <tr><td class="px-4 py-3 font-mono text-xs">{{ $key['name'] ?? '' }}</td><td class="px-4 py-3 font-mono text-xs">{{ implode(', ', $key['columns'] ?? []) }}</td><td class="px-4 py-3 font-mono text-xs">{{ ($key['foreign_schema'] ?? '') ? ($key['foreign_schema'].'.') : '' }}{{ $key['foreign_table'] ?? '' }} ({{ implode(', ', $key['foreign_columns'] ?? []) }})</td><td class="px-4 py-3">{{ $key['on_update'] ?? '' }}</td><td class="px-4 py-3">{{ $key['on_delete'] ?? '' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-xl border bg-white p-8 text-center text-stone-500">No database tables found.</div>
            @endif
        </section>
    </div>
</div>
