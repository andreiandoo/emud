<div>
    <x-admin.page-header title="Calitate & acoperire catalog"
                         subtitle="Indicatori operaționali pentru acoperire, fitment, proveniență și backlog de canonicalizare.">
        <x-slot:actions>
            <button type="button" wire:click="refreshMetrics" class="btn-secondary">
                <x-admin.icon name="refresh" class="h-4 w-4" /> Recalculează
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Piese', $metrics['parts']],
            ['Configurații vehicul', $metrics['vehicles']],
            ['Fitment-uri', $metrics['fitments']],
            ['Numere OEM / IAM / EAN', $metrics['numbers']],
        ] as [$label, $value])
            <x-admin.stat :value="number_format($value, 0, ',', '.')" :label="$label" />
        @endforeach
    </div>

    <div class="mb-8 grid gap-6 lg:grid-cols-2">
        <x-admin.panel title="Acoperire fitment" subtitle="Cât din catalog este efectiv legat de mașini.">
            @foreach([
                ['Piese cu cel puțin un fitment', $metrics['part_fitment_coverage'], $metrics['parts_without_fitments'], 'piese fără fitment'],
                ['Configurații auto cu piese mapate', $metrics['vehicle_fitment_coverage'], $metrics['vehicles_without_fitments'], 'configurații fără fitment'],
            ] as [$label, $percent, $remainder, $remainderLabel])
                <div>
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-stone-600">{{ $label }}</span>
                        <strong class="tabular-nums">{{ $percent }}%</strong>
                    </div>

                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, $percent) }}%"></div>
                    </div>

                    <p class="mt-1 text-xs text-stone-500">{{ number_format($remainder, 0, ',', '.') }} {{ $remainderLabel }}</p>
                </div>
            @endforeach
        </x-admin.panel>

        <x-admin.panel title="Semnale de calitate" subtitle="Ce ar trebui privit înainte de a publica mai departe.">
            <dl class="grid grid-cols-2 gap-4 text-sm">
                @foreach([
                    ['Piese fără numere', $metrics['parts_without_numbers'], null],
                    ['Fitment candidate', $metrics['candidate_fitments'], null],
                    ['Fitment sub 70% încredere', $metrics['low_confidence_fitments'], null],
                    ['Conflicte deschise', $metrics['open_conflicts'], route('admin.catalog-platform.conflicts')],
                    ['Relații nerezolvate', $metrics['pending_relations'], route('admin.catalog-platform.unresolved-relations')],
                    ['Relații cu ≥3 încercări', $metrics['stale_pending_relations'], route('admin.catalog-platform.unresolved-relations')],
                ] as [$label, $value, $link])
                    <div>
                        <dt class="text-stone-500">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums">
                            @if($link)
                                <a href="{{ $link }}" class="hover:underline">{{ number_format($value, 0, ',', '.') }}</a>
                            @else
                                {{ number_format($value, 0, ',', '.') }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-admin.panel>
    </div>

    <x-admin.panel title="Backlog canonicalizare" subtitle="Înregistrări brute care încă nu au ajuns la o decizie." class="mb-8">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['unprocessed' => 'Neprocesate', 'candidate' => 'Candidate', 'ambiguous' => 'Ambigue', 'failed' => 'Eșuate'] as $status => $label)
                <x-admin.stat :value="number_format($metrics['backlog_by_status'][$status] ?? 0, 0, ',', '.')" :label="$label"
                              :tone="$status === 'failed' && ($metrics['backlog_by_status'][$status] ?? 0) > 0 ? 'danger' : 'neutral'" />
            @endforeach
        </div>
    </x-admin.panel>

    <x-admin.section title="Sănătatea surselor">
        <x-slot:aside>Backlogul numără înregistrări neprocesate, candidate, ambigue sau eșuate.</x-slot:aside>

        @if($sources->isEmpty())
            <x-admin.empty title="Nicio sursă configurată" hint="Adaugă o sursă de catalog pentru a începe." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Sursă</th>
                            <th>Drepturi</th>
                            <th class="text-right">Înregistrări</th>
                            <th class="text-right">Backlog</th>
                            <th>Ultimul sync reușit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sources as $source)
                            <tr wire:key="health-{{ $source->id }}">
                                <td>
                                    <a href="{{ route('admin.catalog-platform.sources.edit', $source) }}" class="font-medium text-stone-900 hover:underline">{{ $source->name }}</a>
                                    <div class="font-mono text-xs text-stone-400">{{ $source->code }}</div>
                                </td>
                                <td class="text-stone-600">{{ $source->rights_class->value }}</td>
                                <td class="text-right tabular-nums">{{ number_format($source->records_count, 0, ',', '.') }}</td>
                                <td @class(['text-right tabular-nums', 'font-medium text-amber-700' => $source->backlog_records_count > 0])>
                                    {{ number_format($source->backlog_records_count, 0, ',', '.') }}
                                </td>
                                <td class="whitespace-nowrap text-stone-500">{{ $source->last_successful_sync_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>
                                    <x-admin.status :label="$source->is_active ? 'Activă' : 'Oprită'"
                                                    :tone="$source->is_active ? 'positive' : 'neutral'" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.section>

    {{-- Said out loud, because a figure that is up to fifteen minutes old is a different thing
         from a live one when someone is watching an import. --}}
    <p class="mt-6 text-xs text-stone-400">
        Metricile agregate sunt păstrate în cache 15 minute. Ultima calculare: {{ $metrics['generated_at']->format('d.m.Y H:i:s') }}.
    </p>
</div>
