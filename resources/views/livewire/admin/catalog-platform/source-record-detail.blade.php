<div>
    <a href="{{ route('admin.catalog-platform.source-records', $record->source) }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">
        ← {{ $record->source->name }}
    </a>

    <x-admin.page-header :title="'Înregistrare #'.$record->id"
                         :subtitle="$record->record_type.' · '.$record->external_id" />

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="$record->mapping_status" label="Status"
                      :tone="$record->mapping_status === 'failed' ? 'danger' : ($record->mapping_status === 'ambiguous' ? 'warning' : 'neutral')" />
        <x-admin.stat :value="trim($record->canonical_entity_type.' '.$record->canonical_entity_id) ?: '—'" label="Entitate canonică" />
        <x-admin.stat :value="$record->mapping_confidence ?? '—'" label="Încredere" />
        <x-admin.stat :value="$record->importRun?->uuid ?? '—'" label="Rulare de import" />
    </div>

    @if($record->mapping_notes)
        <p class="mb-8 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $record->mapping_notes }}</p>
    @endif

    <x-admin.section title="Assertions" class="mb-8">
        <x-slot:aside>{{ $record->assertions->count() }}</x-slot:aside>

        @forelse($record->assertions as $assertion)
            <div class="grid gap-2 rounded-xl bg-stone-50 p-3 text-sm md:grid-cols-[16rem_1fr_6rem_6rem]" wire:key="assertion-{{ $assertion->id }}">
                <div class="font-medium text-stone-900">{{ $assertion->field_or_relation }}</div>
                <code class="break-all font-mono text-xs text-stone-600">{{ json_encode($assertion->normalized_value, JSON_UNESCAPED_UNICODE) }}</code>
                <div class="tabular-nums text-stone-600">{{ $assertion->confidence }}</div>
                {{-- Redistribution is a licence question, not a display one, so it is stated per
                     assertion rather than inferred from the source. --}}
                <x-admin.status :label="$assertion->api_redistributable ? 'API permis' : 'API interzis'"
                                :tone="$assertion->api_redistributable ? 'positive' : 'neutral'" />
            </div>
        @empty
            <p class="text-sm text-stone-500">Nicio assertion publicată încă.</p>
        @endforelse
    </x-admin.section>

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach([['Payload brut', $record->raw_payload], ['Payload normalizat', $record->normalized_payload]] as [$label, $payload])
            <div class="rounded-xl bg-stone-950 p-5 text-stone-100">
                <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-stone-400">{{ $label }}</h2>
                <pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-all text-xs">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endforeach
    </div>
</div>
