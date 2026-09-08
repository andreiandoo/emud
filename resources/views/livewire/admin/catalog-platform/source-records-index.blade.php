<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><div class="text-sm text-stone-500">Sursă</div><h1 class="text-2xl font-semibold tracking-tight">{{ $source->name }} · Raw records</h1></div>
        <a href="{{ route('admin.catalog-platform.sources.edit', $source) }}" class="rounded-lg border bg-white px-4 py-2 text-sm">Configurare sursă</a>
    </div>
    <div class="grid gap-3 rounded-xl bg-white p-4 shadow-sm md:grid-cols-4">
        <input wire:model.live.debounce.300ms="search"  placeholder="External ID / eroare...">
        <select wire:model.live="status" ><option value="">Toate statusurile</option>@foreach(['unprocessed','published','skipped','ambiguous','failed'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select>
        <select wire:model.live="type" ><option value="">Toate tipurile</option>@foreach($types as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select>
        <div class="text-right text-sm text-stone-500">{{ number_format($records->total()) }} records</div>
    </div>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm"><table class="min-w-full text-sm"><thead class="bg-stone-50 text-left"><tr><th class="p-3">ID</th><th class="p-3">Tip</th><th class="p-3">External ID</th><th class="p-3">Status</th><th class="p-3">Canonical</th><th class="p-3">Confidence</th><th class="p-3">Văzut</th></tr></thead><tbody class="divide-y">@foreach($records as $record)<tr class="hover:bg-stone-50"><td class="p-3"><a class="font-semibold underline" href="{{ route('admin.catalog-platform.source-records.show', $record) }}">{{ $record->id }}</a></td><td class="p-3">{{ $record->record_type }}</td><td class="p-3 max-w-xs truncate">{{ $record->external_id }}</td><td class="p-3">{{ $record->mapping_status }}</td><td class="p-3">{{ $record->canonical_entity_type }} {{ $record->canonical_entity_id }}</td><td class="p-3">{{ $record->mapping_confidence }}</td><td class="p-3">{{ $record->last_seen_at?->diffForHumans() }}</td></tr>@endforeach</tbody></table></div>
    {{ $records->links() }}
</div>
