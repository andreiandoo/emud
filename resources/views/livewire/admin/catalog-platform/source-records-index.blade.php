<div>
    <x-admin.page-header :title="$source->name" subtitle="Înregistrări brute, exact așa cum au venit de la sursă.">
        <x-slot:actions>
            <a href="{{ route('admin.catalog-platform.sources.edit', $source) }}" class="btn-secondary">Configurare sursă</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Caută înregistrare</span>
            <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
            <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="External ID / eroare...">
        </label>

        <label class="w-full sm:w-48">
            <span class="sr-only">Status</span>
            <select wire:model.live="status">
                <option value="">Toate statusurile</option>
                @foreach(['unprocessed', 'published', 'skipped', 'ambiguous', 'failed'] as $value)
                    <option value="{{ $value }}">{{ $value }}</option>
                @endforeach
            </select>
        </label>

        <label class="w-full sm:w-48">
            <span class="sr-only">Tip</span>
            <select wire:model.live="type">
                <option value="">Toate tipurile</option>
                @foreach($types as $value)
                    <option value="{{ $value }}">{{ $value }}</option>
                @endforeach
            </select>
        </label>

        <span class="ml-auto text-sm text-stone-500">{{ number_format($records->total(), 0, ',', '.') }} înregistrări</span>
    </div>

    @if($records->isEmpty())
        <x-admin.empty title="Nicio înregistrare" hint="Nimic nu se potrivește cu filtrele curente." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tip</th>
                        <th>External ID</th>
                        <th>Status</th>
                        <th>Canonic</th>
                        <th class="text-right">Încredere</th>
                        <th>Văzut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        <tr wire:key="record-{{ $record->id }}">
                            <td>
                                <a href="{{ route('admin.catalog-platform.source-records.show', $record) }}"
                                   class="font-medium text-stone-900 hover:underline">{{ $record->id }}</a>
                            </td>
                            <td class="text-stone-600">{{ $record->record_type }}</td>
                            <td class="max-w-xs truncate font-mono text-xs text-stone-600">{{ $record->external_id }}</td>
                            <td>
                                <x-admin.status :label="$record->mapping_status" :tone="match ($record->mapping_status) {
                                    'published' => 'positive',
                                    'failed' => 'danger',
                                    'ambiguous' => 'warning',
                                    'skipped' => 'neutral',
                                    default => 'info',
                                }" />
                            </td>
                            <td class="text-stone-600">
                                {{ $record->canonical_entity_type ? $record->canonical_entity_type.' '.$record->canonical_entity_id : '—' }}
                            </td>
                            <td class="text-right tabular-nums">{{ $record->mapping_confidence ?? '—' }}</td>
                            <td class="whitespace-nowrap text-stone-500">{{ $record->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $records->links() }}</div>
    @endif
</div>
