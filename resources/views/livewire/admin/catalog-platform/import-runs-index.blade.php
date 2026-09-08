<div>
    <x-admin.page-header title="Rulări de import" subtitle="Fiecare execuție a unei surse tehnice și contoarele ei." />

    @if($runs->isEmpty())
        <x-admin.empty title="Nicio rulare" hint="Pornește o sincronizare dintr-o sursă de catalog." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Pornită</th>
                        <th>Sursă</th>
                        <th>Mod</th>
                        <th>Status</th>
                        <th class="text-right">Preluate</th>
                        <th class="text-right">Potrivite</th>
                        <th class="text-right">Publicate</th>
                        <th class="text-right">Eșuate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($runs as $run)
                        @php($status = $run->status->value ?? $run->status)
                        <tr wire:key="run-{{ $run->id }}">
                            <td class="whitespace-nowrap text-stone-500">{{ $run->started_at?->format('d.m.Y H:i:s') ?? '—' }}</td>
                            <td class="font-mono text-xs font-medium text-stone-900">{{ $run->source?->code }}</td>
                            <td class="text-stone-600">{{ $run->mode }}</td>
                            <td>
                                <x-admin.status :label="ucfirst(str_replace('_', ' ', $status))" :tone="match ($status) {
                                    'completed' => 'positive',
                                    'failed', 'aborted_guard' => 'danger',
                                    'completed_with_errors' => 'warning',
                                    'running' => 'info',
                                    default => 'neutral',
                                }" />
                            </td>
                            <td class="text-right tabular-nums">{{ number_format($run->fetched_count, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($run->matched_count, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($run->published_count, 0, ',', '.') }}</td>
                            <td @class(['text-right tabular-nums', 'font-medium text-red-700' => $run->failed_count > 0])>
                                {{ number_format($run->failed_count, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</div>
