<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Calitate & acoperire catalog</h1>
            <p class="text-sm text-stone-500">Indicatori operaționali pentru acoperire, fitment, proveniență și backlog de canonicalizare.</p>
        </div>
        <button wire:click="refreshMetrics" class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium">Recalculează</button>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Piese', $metrics['parts']],
            ['Configurații vehicul', $metrics['vehicles']],
            ['Fitment-uri', $metrics['fitments']],
            ['Numere OEM / IAM / EAN', $metrics['numbers']],
        ] as [$label, $value])
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <div class="text-sm text-stone-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{{ number_format($value, 0, ',', '.') }}</div>
            </div>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <div class="card-padded">
            <h2 class="font-semibold">Acoperire fitment</h2>
            <div class="mt-4 space-y-4">
                <div>
                    <div class="flex justify-between text-sm"><span>Piese cu cel puțin un fitment</span><strong>{{ $metrics['part_fitment_coverage'] }}%</strong></div>
                    <div class="mt-2 h-2 overflow-hidden rounded bg-stone-100"><div class="btn-primary" style="width: {{ min(100, $metrics['part_fitment_coverage']) }}%"></div></div>
                    <div class="mt-1 text-xs text-stone-500">{{ number_format($metrics['parts_without_fitments'], 0, ',', '.') }} piese fără fitment</div>
                </div>
                <div>
                    <div class="flex justify-between text-sm"><span>Configurații auto cu piese mapate</span><strong>{{ $metrics['vehicle_fitment_coverage'] }}%</strong></div>
                    <div class="mt-2 h-2 overflow-hidden rounded bg-stone-100"><div class="btn-primary" style="width: {{ min(100, $metrics['vehicle_fitment_coverage']) }}%"></div></div>
                    <div class="mt-1 text-xs text-stone-500">{{ number_format($metrics['vehicles_without_fitments'], 0, ',', '.') }} configurații fără fitment</div>
                </div>
            </div>
        </div>

        <div class="card-padded">
            <h2 class="font-semibold">Semnale de calitate</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-stone-500">Piese fără numere</dt><dd class="mt-1 text-lg font-semibold">{{ number_format($metrics['parts_without_numbers'], 0, ',', '.') }}</dd></div>
                <div><dt class="text-stone-500">Fitment candidate</dt><dd class="mt-1 text-lg font-semibold">{{ number_format($metrics['candidate_fitments'], 0, ',', '.') }}</dd></div>
                <div><dt class="text-stone-500">Fitment &lt; 70% confidence</dt><dd class="mt-1 text-lg font-semibold">{{ number_format($metrics['low_confidence_fitments'], 0, ',', '.') }}</dd></div>
                <div><dt class="text-stone-500">Conflicte deschise</dt><dd class="mt-1 text-lg font-semibold"><a href="{{ route('admin.catalog-platform.conflicts') }}" class="hover:underline">{{ number_format($metrics['open_conflicts'], 0, ',', '.') }}</a></dd></div>
                <div><dt class="text-stone-500">Relații nerezolvate</dt><dd class="mt-1 text-lg font-semibold"><a href="{{ route('admin.catalog-platform.unresolved-relations') }}" class="hover:underline">{{ number_format($metrics['pending_relations'], 0, ',', '.') }}</a></dd></div>
                <div><dt class="text-stone-500">Relații cu ≥3 încercări</dt><dd class="mt-1 text-lg font-semibold"><a href="{{ route('admin.catalog-platform.unresolved-relations') }}" class="hover:underline">{{ number_format($metrics['stale_pending_relations'], 0, ',', '.') }}</a></dd></div>
            </dl>
        </div>
    </section>

    <section class="card-padded">
        <h2 class="font-semibold">Backlog canonicalizare</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['unprocessed' => 'Neprocesate', 'candidate' => 'Candidate', 'ambiguous' => 'Ambigue', 'failed' => 'Eșuate'] as $status => $label)
                <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs uppercase tracking-wide text-stone-500">{{ $label }}</div><div class="mt-1 text-lg font-semibold">{{ number_format($metrics['backlog_by_status'][$status] ?? 0, 0, ',', '.') }}</div></div>
            @endforeach
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="border-b border-stone-100 p-5"><h2 class="font-semibold">Sănătatea surselor</h2><p class="text-sm text-stone-500">Backlog-ul este calculat din înregistrări neprocesate, candidate, ambigue sau eșuate.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Sursă</th><th class="px-4 py-3">Drepturi</th><th class="px-4 py-3 text-right">Records</th><th class="px-4 py-3 text-right">Backlog</th><th class="px-4 py-3">Ultimul sync reușit</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse($sources as $source)
                        <tr><td class="px-4 py-3"><a href="{{ route('admin.catalog-platform.sources.edit', $source) }}" class="font-semibold hover:underline">{{ $source->name }}</a><div class="text-xs text-stone-400">{{ $source->code }}</div></td><td class="px-4 py-3">{{ $source->rights_class->value }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($source->records_count, 0, ',', '.') }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($source->backlog_records_count, 0, ',', '.') }}</td><td class="px-4 py-3">{{ $source->last_successful_sync_at?->format('Y-m-d H:i') ?? '—' }}</td><td class="px-4 py-3"><span @class(['rounded-full px-2 py-1 text-xs','bg-emerald-100 text-emerald-900' => $source->is_active,'bg-stone-100 text-stone-500' => ! $source->is_active])>{{ $source->is_active ? 'activă' : 'oprită' }}</span></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-stone-500">Nu există surse catalog configurate.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="text-xs text-stone-400">Metricile agregate sunt cache-uite 15 minute. Ultima calculare: {{ $metrics['generated_at']->format('Y-m-d H:i:s') }}.</p>
</div>
