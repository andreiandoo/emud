<div>
    <x-admin.page-header title="Surse ateliere" subtitle="De unde vin datele registrului, cât de proaspete sunt și ce poate fi publicat.">
        <x-slot:actions>
            <a href="{{ route('admin.workshops.index') }}" class="btn-secondary">Registru național</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="number_format($overview['workshops'], 0, ',', '.')" label="Ateliere unice" />
        <x-admin.stat :value="number_format($overview['rar_authorized'], 0, ',', '.')" label="Autorizate RAR service" />
        <x-admin.stat :value="number_format($overview['with_phone'], 0, ',', '.')" label="Cu telefon" />
        <x-admin.stat :value="number_format($overview['with_email'], 0, ',', '.')" label="Cu email" />
        <x-admin.stat :value="number_format($overview['supports_4x4'], 0, ',', '.')" label="4x4 (tracțiune pe mai multe axe)" />
        <x-admin.stat :value="number_format($overview['offroad_relevant'], 0, ',', '.')" label="Relevante off-road (≥ 40)" />
        <x-admin.stat :value="number_format($overview['with_precise_coordinates'], 0, ',', '.')" label="Cu coordonate la nivel de stradă" />
        <x-admin.stat :value="number_format($overview['failed_records'], 0, ',', '.')" label="Înregistrări eșuate" :tone="$overview['failed_records'] > 0 ? 'warning' : 'neutral'" />
    </div>

    <x-admin.section title="Surse" class="mb-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr><th>Sursă</th><th>Tip</th><th class="text-right">Înregistrări</th><th class="text-right">Curente</th><th class="text-right">Eșuate</th><th>Ultimul import</th><th>Activă</th><th>Publicabilă</th></tr>
                </thead>
                <tbody>
                    @forelse($sources as $source)
                        <tr wire:key="source-{{ $source->id }}">
                            <td>
                                <div class="font-medium text-stone-900">{{ $source->name }}</div>
                                <div class="font-mono text-xs text-stone-500">{{ $source->key }}</div>
                            </td>
                            <td class="text-stone-600">{{ $source->type }}</td>
                            <td class="text-right tabular-nums">{{ number_format($source->records_count, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($source->current_records_count, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ $source->failed_records_count }}</td>
                            <td class="whitespace-nowrap text-stone-500">{{ $source->last_completed_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>
                                <div class="flex items-center gap-3 whitespace-nowrap">
                                    <x-admin.status :label="$source->is_enabled ? 'da' : 'nu'" :tone="$source->is_enabled ? 'positive' : 'neutral'" />
                                    <button type="button" class="btn-secondary" wire:click="toggleEnabled({{ $source->id }})">
                                        {{ $source->is_enabled ? 'Dezactivează' : 'Activează' }}
                                    </button>
                                </div>
                            </td>
                            <td>
                                {{-- Publication is a licensing decision, taken per source and never by default. --}}
                                <div class="flex items-center gap-3 whitespace-nowrap">
                                    <x-admin.status :label="$source->is_public_output_allowed ? 'da' : 'doar intern'" :tone="$source->is_public_output_allowed ? 'positive' : 'neutral'" />
                                    <button type="button" class="btn-secondary" wire:click="togglePublic({{ $source->id }})"
                                            wire:confirm="{{ $source->is_public_output_allowed
                                                ? 'Datele din „'.$source->name.'” nu vor mai apărea public. Continui?'
                                                : 'Datele din „'.$source->name.'” vor putea apărea public (directorul de service-uri, exporturile publice). Ai verificat condițiile de reutilizare ale sursei?' }}">
                                        {{ $source->is_public_output_allowed ? 'Fă internă' : 'Fă publicabilă' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-stone-500">Nicio sursă importată încă. Începe cu php artisan workshops:rar:discover și workshops:rar:import.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.section>

    <x-admin.section title="Ultimele rulări de import" class="mb-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr><th>Sursă</th><th>Status</th><th>Început</th><th class="text-right">Văzute</th><th class="text-right">Noi</th><th class="text-right">Schimbate</th><th class="text-right">Neschimbate</th><th class="text-right">Eșuate</th><th class="text-right">Retrase</th></tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr wire:key="run-{{ $run->id }}">
                            <td class="font-mono text-xs">{{ $run->dataSource?->key }}</td>
                            <td>
                                <x-admin.status :label="$run->status->label()" :tone="match ($run->status->value) {
                                    'completed' => 'positive',
                                    'failed' => 'danger',
                                    'completed_with_errors' => 'warning',
                                    'running' => 'info',
                                    default => 'neutral',
                                }" />
                                @if($run->error_summary)<div class="max-w-xs truncate text-xs text-red-700" title="{{ $run->error_summary }}">{{ $run->error_summary }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap text-stone-500">{{ $run->started_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') }}</td>
                            <td class="text-right tabular-nums">{{ $run->discovered_count }}</td>
                            <td class="text-right tabular-nums">{{ $run->created_count }}</td>
                            <td class="text-right tabular-nums">{{ $run->updated_count }}</td>
                            <td class="text-right tabular-nums">{{ $run->unchanged_count }}</td>
                            <td class="text-right tabular-nums">{{ $run->failed_count }}</td>
                            <td class="text-right tabular-nums">{{ $run->retired_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-stone-500">Nicio rulare încă.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.section>

    <x-admin.section title="Acoperire pe județe">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th>Județ</th><th class="text-right">Ateliere</th><th class="text-right">RAR service</th><th class="text-right">ITP</th><th class="text-right">Cu telefon</th></tr></thead>
                <tbody>
                    @foreach($counties as $row)
                        <tr wire:key="county-{{ $row['code'] }}">
                            <td><a href="{{ route('admin.workshops.index', ['county' => $row['code']]) }}" class="hover:underline">{{ $row['county'] }}</a></td>
                            <td class="text-right tabular-nums">{{ $row['workshops'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['rar'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['itp'] }}</td>
                            <td class="text-right tabular-nums">{{ $row['with_phone'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.section>
</div>
