<form wire:submit="save">
    <a href="{{ route('admin.catalog-platform.sources') }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">← Surse de catalog</a>

    <x-admin.page-header :title="$source?->exists ? 'Editează sursa' : 'Sursă nouă'"
                         :subtitle="$source?->exists
                            ? 'Ultima încercare: '.($source->last_attempted_sync_at?->format('d.m.Y H:i:s') ?? 'niciodată').' · Ultimul succes: '.($source->last_successful_sync_at?->format('d.m.Y H:i:s') ?? 'niciodată')
                            : 'Conexiune, drepturi de utilizare și program de import.'">
        @if($source?->exists)
            <x-slot:actions>
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" class="btn-secondary">Testează conexiunea</button>
                <button type="button" wire:click="runNow('catalog')" class="btn-secondary">Rulează catalogul acum</button>
                <button type="button" wire:click="runConfiguredModes" class="btn-primary">Rulează modurile configurate</button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <div class="space-y-6">
        @if(session('status'))
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif

        @if(session('operationStatus'))
            <p class="rounded-lg border border-sky-300 bg-sky-50 p-3 text-sm text-sky-900">{{ session('operationStatus') }}</p>
        @endif

        @error('connection')
            <p class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>
        @enderror

        @if($connectionResult)
            <section @class([
                'rounded-xl border p-5',
                'border-emerald-300 bg-emerald-50' => $connectionResult['ok'],
                'border-red-300 bg-red-50' => ! $connectionResult['ok'],
            ])>
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-semibold">Test conexiune: {{ $connectionResult['ok'] ? 'OK' : 'EȘUAT' }}</h2>
                    <span class="text-xs text-stone-500">{{ $connectionResult['tested_at'] }}</span>
                </div>

                <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                    @foreach($connectionResult['details'] as $key => $value)
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-stone-500">{{ $key }}</dt>
                            <dd class="break-all font-mono text-xs">{{ is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <x-admin.panel title="Conexiune" subtitle="Cum ajunge platforma la datele sursei.">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="field-label">Nume</span>
                    <input wire:model="name">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Cod</span>
                    <input wire:model="code" class="font-mono text-sm">
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Tip sursă</span>
                    <input wire:model="sourceType">
                </label>

                <label class="block">
                    <span class="field-label">Protocol</span>
                    <select wire:model="protocol">
                        <option>http</option><option>database</option><option>sftp</option><option>ftp</option><option>file</option>
                    </select>
                </label>

                <label class="block md:col-span-2">
                    <span class="field-label">Clasă de conector</span>
                    <input wire:model="connectorClass" class="font-mono text-sm" placeholder="App\Catalog\Sources\Connectors\...">
                </label>

                <label class="block md:col-span-2">
                    <span class="field-label">Clasă de canonicalizare</span>
                    <input wire:model="canonicalizerClass" class="font-mono text-sm" placeholder="App\Catalog\Canonicalization\Parts\ManufacturerPartCanonicalizer">
                    <span class="field-hint">
                        Folosește canonicalizatorul generic pentru feeduri structurate brand/MPN/OE/EAN/fitment;
                        o clasă specifică sursei doar când chiar e nevoie.
                    </span>
                </label>

                <label class="block">
                    <span class="field-label">Base URL</span>
                    <input wire:model="baseUrl">
                </label>

                <label class="block">
                    <span class="field-label">Endpoint catalog</span>
                    <input wire:model="catalogEndpoint">
                </label>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Drepturi de utilizare"
                       subtitle="Ce se poate face cu datele acestei surse. Fiecare bifă are o consecință contractuală.">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="field-label">Clasă de drepturi</span>
                    <select wire:model="rightsClass">
                        @foreach($rightsClasses as $rights)<option value="{{ $rights->value }}">{{ $rights->value }}</option>@endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Nume licență</span>
                    <input wire:model="licenseName">
                </label>

                <label class="block md:col-span-2">
                    <span class="field-label">URL licență</span>
                    <input type="url" wire:model="licenseUrl">
                </label>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([
                    'allowInternal' => 'Uz intern',
                    'allowEcommerce' => 'Magazin',
                    'allowDerived' => 'Date derivate',
                    'allowApiRedistribution' => 'API public',
                    'allowBulkExport' => 'Export în masă',
                    'allowMediaRedistribution' => 'Redistribuire media',
                    'attributionRequired' => 'Atribuire obligatorie',
                    'isActive' => 'Activă',
                ] as $property => $label)
                    <label class="flex items-center gap-2 rounded-lg bg-stone-50 p-3">
                        <input type="checkbox" wire:model="{{ $property }}">
                        <span class="text-sm text-stone-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <label class="block">
                <span class="field-label">Note legale</span>
                <textarea wire:model="legalNotes" rows="5"></textarea>
            </label>
        </x-admin.panel>

        <x-admin.panel title="Program de import"
                       subtitle="Fiecare mod are propriul cron și fus orar. Scheduler-ul Laravel lansează rândurile scadente.">
            <x-slot:actions>
                <button type="button" wire:click="addSchedule" class="btn-secondary">+ Adaugă program</button>
            </x-slot:actions>

            @forelse($schedules as $index => $schedule)
                <div wire:key="source-schedule-{{ $index }}" class="grid items-start gap-4 rounded-xl bg-stone-50 p-4 md:grid-cols-[1fr_1.5fr_1.2fr_auto_auto]">
                    <label class="block">
                        <span class="field-label">Mod</span>
                        <input wire:model="schedules.{{ $index }}.mode" placeholder="catalog">
                        @error("schedules.$index.mode") <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Cron</span>
                        <input wire:model="schedules.{{ $index }}.cron_expression" class="font-mono text-sm" placeholder="0 2 * * *">
                        @error("schedules.$index.cron_expression") <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Fus orar</span>
                        <input wire:model="schedules.{{ $index }}.timezone" placeholder="Europe/Bucharest">
                        @error("schedules.$index.timezone") <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="flex items-center gap-2 pt-7 text-sm text-stone-700">
                        <input type="checkbox" wire:model="schedules.{{ $index }}.is_enabled">
                        Activ
                    </label>

                    <div class="flex gap-2 pt-6">
                        <button type="button" wire:click="runNow('{{ $schedule['mode'] ?: 'catalog' }}')" class="btn-secondary">Rulează</button>
                        <button type="button" wire:click="removeSchedule({{ $index }})" class="btn-danger">Elimină</button>
                    </div>

                    @if($schedule['last_dispatched_at'])
                        <p class="text-xs text-stone-400 md:col-span-5">Ultima lansare: {{ $schedule['last_dispatched_at'] }}</p>
                    @endif
                </div>
            @empty
                <p class="rounded-xl bg-stone-50 p-4 text-sm text-stone-500">
                    Niciun program. Sursa poate fi rulată în continuare manual.
                </p>
            @endforelse
        </x-admin.panel>

        <x-admin.panel title="Configurare brută"
                       subtitle="Credențialele sunt criptate la repaus de Laravel. Nu le pune în Git.">
            <div class="grid gap-4 xl:grid-cols-2">
                @foreach([
                    'credentialsJson' => 'Credențiale (JSON)',
                    'settingsJson' => 'Setări (JSON)',
                    'mappingJson' => 'Mapare de câmpuri (JSON)',
                    'capabilitiesJson' => 'Capabilități (JSON)',
                ] as $property => $label)
                    <label class="block">
                        <span class="field-label">{{ $label }}</span>
                        <textarea wire:model="{{ $property }}" rows="12" class="font-mono text-xs"></textarea>
                        @error($property) <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                @endforeach
            </div>
        </x-admin.panel>

        <button type="submit" class="btn-primary">Salvează sursa și programele</button>
    </div>
</form>
