<div>
    <a href="{{ route('admin.workshops.sources') }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">← Surse ateliere</a>

    <x-admin.page-header :title="'Înregistrare #'.$record->id" :subtitle="($record->dataSource?->name ?? '?').' · '.$record->record_type.' · '.$record->external_id" />

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="$record->parse_status" label="Status citire" :tone="$record->parse_status === 'failed' ? 'danger' : 'neutral'" />
        <x-admin.stat :value="$record->is_current ? 'da' : 'nu'" label="Mai e listată de sursă" :tone="$record->is_current ? 'neutral' : 'warning'" />
        <x-admin.stat :value="$record->first_seen_at?->format('d.m.Y')" label="Văzută prima dată" />
        <x-admin.stat :value="$record->last_seen_at?->format('d.m.Y H:i')" label="Văzută ultima dată" />
    </div>

    @if($record->parse_error)
        <p class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ $record->parse_error }}</p>
    @endif

    <x-admin.panel title="Proveniență" class="mb-8">
        <x-admin.definition cols="wide" :rows="[
            'Sursă' => $record->dataSource?->key,
            'Identitate stabilă' => $record->identity_key,
            'Județ' => $record->county_code,
            'Adresă sursă' => $record->source_reference,
            'HTTP' => $record->http_status,
            'SHA-256' => $record->content_hash,
            'Conținut schimbat la' => $record->content_changed_at?->format('d.m.Y H:i'),
            'Rulare import' => $record->importRun?->uuid,
            'Atelier' => $record->link?->workshop?->name,
        ]" />
        @if($record->link?->workshop)
            <a href="{{ route('admin.workshops.show', $record->link->workshop) }}" class="text-sm underline">Deschide atelierul</a>
        @endif
    </x-admin.panel>

    @if($record->matches->isNotEmpty())
        <x-admin.section title="Decizii de potrivire" class="mb-8">
            @foreach($record->matches as $match)
                <div class="rounded-xl bg-stone-50 p-3 text-sm" wire:key="match-{{ $match->id }}">
                    {{ $match->target_type }} #{{ $match->target_id ?? '—' }} · {{ $match->status->value }} · {{ $match->method }} · scor {{ $match->score ?? '—' }}
                    @if($match->reviewed_by) · decis manual @endif
                </div>
            @endforeach
        </x-admin.section>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        @if($record->payload !== null)
            <div class="rounded-xl bg-stone-950 p-5 text-stone-100">
                <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-stone-400">Payload</h2>
                <pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap break-all text-xs">{{ json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif

        @if($rawExcerpt !== null)
            <div class="rounded-xl bg-stone-950 p-5 text-stone-100">
                <h2 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-stone-400">Conținut brut (primele 20 000 de caractere, text)</h2>
                <pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap break-all text-xs">{{ $rawExcerpt }}</pre>
            </div>
        @endif
    </div>
</div>
