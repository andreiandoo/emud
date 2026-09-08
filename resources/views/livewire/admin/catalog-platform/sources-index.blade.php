<div>
    <x-admin.page-header title="Surse de catalog" subtitle="Surse tehnice, drepturi de utilizare, importuri și acoperire.">
        <x-slot:actions>
            <a href="{{ route('admin.catalog-platform.sources.create') }}" class="btn-primary">Sursă nouă</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
    @endif

    <label class="relative mb-4 block max-w-md">
        <span class="sr-only">Caută sursă</span>
        <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Caută sursă...">
    </label>

    @if($sources->isEmpty())
        <x-admin.empty title="Nicio sursă" hint="Adaugă o sursă tehnică pentru a începe importul catalogului.">
            <a href="{{ route('admin.catalog-platform.sources.create') }}" class="btn-primary">Adaugă o sursă</a>
        </x-admin.empty>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Sursă</th>
                        <th>Tip</th>
                        <th>Drepturi</th>
                        <th class="text-right">Înregistrări</th>
                        <th class="text-right">Rulări</th>
                        <th>Ultimul succes</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sources as $source)
                        <tr wire:key="source-{{ $source->id }}">
                            <td>
                                <a href="{{ route('admin.catalog-platform.sources.edit', $source) }}" class="font-medium text-stone-900 hover:underline">{{ $source->name }}</a>
                                <div class="font-mono text-xs text-stone-500">{{ $source->code }}</div>
                            </td>

                            <td class="text-stone-600">{{ $source->source_type }}</td>

                            <td>
                                <div class="text-stone-700">{{ $source->rights_class->value ?? $source->rights_class }}</div>
                                {{-- Redistribution is the one flag with a legal consequence, so it
                                     is spelled out rather than left to the rights class alone. --}}
                                <x-admin.status :label="$source->allow_api_redistribution ? 'API permis' : 'API interzis'"
                                                :tone="$source->allow_api_redistribution ? 'positive' : 'warning'" />
                            </td>

                            <td class="text-right tabular-nums">{{ number_format($source->records_count, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums">{{ number_format($source->import_runs_count, 0, ',', '.') }}</td>

                            <td class="whitespace-nowrap text-stone-500">
                                {{ $source->last_successful_sync_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>

                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action wire:click="sync({{ $source->id }}, 'catalog')">Pornește sincronizarea</x-admin.row-action>
                                    <x-admin.row-action href="{{ route('admin.catalog-platform.sources.edit', $source) }}">Configurare</x-admin.row-action>
                                    <x-admin.row-action href="{{ route('admin.catalog-platform.source-records', $source) }}">Înregistrări brute</x-admin.row-action>
                                    <x-admin.row-action wire:click="toggle({{ $source->id }})">{{ $source->is_active ? 'Pune pe pauză' : 'Activează' }}</x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $sources->links() }}</div>
    @endif
</div>
