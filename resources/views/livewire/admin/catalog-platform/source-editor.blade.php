<form wire:submit="save" class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <a href="{{ route('admin.catalog-platform.sources') }}" class="text-sm text-stone-500">← Catalog sources</a>
            <h1 class="mt-2 text-2xl font-bold">{{ $source?->exists ? 'Edit source' : 'New source' }}</h1>
            @if($source?->exists)
                <p class="mt-1 text-sm text-stone-500">Last attempted: {{ $source->last_attempted_sync_at?->format('Y-m-d H:i:s') ?? 'never' }} · Last successful: {{ $source->last_successful_sync_at?->format('Y-m-d H:i:s') ?? 'never' }}</p>
            @endif
        </div>
        @if($source?->exists)
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold hover:bg-stone-50">Test connection</button>
                <button type="button" wire:click="runNow('catalog')" class="rounded-lg border bg-white px-4 py-2 text-sm font-semibold hover:bg-stone-50">Run catalog now</button>
                <button type="button" wire:click="runConfiguredModes" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Run configured modes</button>
            </div>
        @endif
    </div>

    @if(session('status'))<div class="rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>@endif
    @if(session('operationStatus'))<div class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">{{ session('operationStatus') }}</div>@endif
    @error('connection')<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</div>@enderror

    @if($connectionResult)
        <section @class(['rounded-xl border p-4','border-lime-200 bg-lime-50'=>$connectionResult['ok'],'border-red-200 bg-red-50'=>!$connectionResult['ok']])>
            <div class="flex items-center justify-between gap-4"><h2 class="font-bold">Connection test: {{ $connectionResult['ok'] ? 'OK' : 'FAILED' }}</h2><span class="text-xs text-stone-500">{{ $connectionResult['tested_at'] }}</span></div>
            <dl class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                @foreach($connectionResult['details'] as $key=>$value)
                    <div><dt class="text-xs uppercase tracking-wide text-stone-500">{{ $key }}</dt><dd class="break-all font-mono text-xs">{{ is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null') }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2">
        <label class="space-y-1"><span class="text-sm font-medium">Name</span><input wire:model="name" class="w-full rounded border px-3 py-2">@error('name')<small class="text-red-600">{{ $message }}</small>@enderror</label>
        <label class="space-y-1"><span class="text-sm font-medium">Code</span><input wire:model="code" class="w-full rounded border px-3 py-2">@error('code')<small class="text-red-600">{{ $message }}</small>@enderror</label>
        <label><span class="text-sm">Source type</span><input wire:model="sourceType" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label><span class="text-sm">Protocol</span><select wire:model="protocol" class="mt-1 w-full rounded border px-3 py-2"><option>http</option><option>database</option><option>sftp</option><option>ftp</option><option>file</option></select></label>
        <label class="md:col-span-2"><span class="text-sm">Connector class</span><input wire:model="connectorClass" class="mt-1 w-full rounded border px-3 py-2" placeholder="App\Catalog\Sources\Connectors\..."></label>
        <label class="md:col-span-2"><span class="text-sm">Canonicalizer class</span><input wire:model="canonicalizerClass" class="mt-1 w-full rounded border px-3 py-2" placeholder="App\Catalog\Canonicalization\Parts\ManufacturerPartCanonicalizer"><span class="text-xs text-stone-500">Use the generic manufacturer canonicalizer for structured brand/MPN/OE/EAN/fitment feeds; use a source-specific class only when needed.</span></label>
        <label><span class="text-sm">Base URL</span><input wire:model="baseUrl" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label><span class="text-sm">Catalog endpoint</span><input wire:model="catalogEndpoint" class="mt-1 w-full rounded border px-3 py-2"></label>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="mb-4 font-bold">Rights</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <label><span class="text-sm">Rights class</span><select wire:model="rightsClass" class="mt-1 w-full rounded border px-3 py-2">@foreach($rightsClasses as $rights)<option value="{{ $rights->value }}">{{ $rights->value }}</option>@endforeach</select></label>
            <label><span class="text-sm">License name</span><input wire:model="licenseName" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="md:col-span-2"><span class="text-sm">License URL</span><input wire:model="licenseUrl" class="mt-1 w-full rounded border px-3 py-2"></label>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">@foreach(['allowInternal'=>'Internal','allowEcommerce'=>'Ecommerce','allowDerived'=>'Derived','allowApiRedistribution'=>'Public API','allowBulkExport'=>'Bulk export','allowMediaRedistribution'=>'Media redistribution','attributionRequired'=>'Attribution required','isActive'=>'Active'] as $property=>$label)<label class="flex items-center gap-2 rounded border p-3"><input type="checkbox" wire:model="{{ $property }}"><span class="text-sm">{{ $label }}</span></label>@endforeach</div>
        <label class="mt-4 block"><span class="text-sm">Legal notes</span><textarea wire:model="legalNotes" rows="5" class="mt-1 w-full rounded border p-3"></textarea></label>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-bold">Import schedules</h2><p class="text-sm text-stone-500">Each mode has its own cron and timezone. The Laravel scheduler dispatches due rows.</p></div><button type="button" wire:click="addSchedule" class="rounded-lg border px-3 py-2 text-sm font-semibold">+ Add schedule</button></div>
        <div class="mt-4 space-y-3">
            @forelse($schedules as $index=>$schedule)
                <div wire:key="source-schedule-{{ $index }}" class="grid gap-3 rounded-lg border p-4 md:grid-cols-[1fr_1.5fr_1.2fr_auto_auto] md:items-start">
                    <label><span class="text-xs font-medium uppercase text-stone-500">Mode</span><input wire:model="schedules.{{ $index }}.mode" class="mt-1 w-full rounded border px-3 py-2 text-sm" placeholder="catalog">@error("schedules.$index.mode")<small class="text-red-600">{{ $message }}</small>@enderror</label>
                    <label><span class="text-xs font-medium uppercase text-stone-500">Cron</span><input wire:model="schedules.{{ $index }}.cron_expression" class="mt-1 w-full rounded border px-3 py-2 font-mono text-sm" placeholder="0 2 * * *">@error("schedules.$index.cron_expression")<small class="text-red-600">{{ $message }}</small>@enderror</label>
                    <label><span class="text-xs font-medium uppercase text-stone-500">Timezone</span><input wire:model="schedules.{{ $index }}.timezone" class="mt-1 w-full rounded border px-3 py-2 text-sm" placeholder="Europe/Bucharest">@error("schedules.$index.timezone")<small class="text-red-600">{{ $message }}</small>@enderror</label>
                    <label class="mt-6 flex items-center gap-2"><input type="checkbox" wire:model="schedules.{{ $index }}.is_enabled"><span class="text-sm">Enabled</span></label>
                    <div class="mt-5 flex gap-2"><button type="button" wire:click="runNow('{{ $schedule['mode'] ?: 'catalog' }}')" class="rounded border px-2 py-1 text-xs">Run</button><button type="button" wire:click="removeSchedule({{ $index }})" class="rounded border border-red-200 px-2 py-1 text-xs text-red-700">Remove</button></div>
                    @if($schedule['last_dispatched_at'])<p class="text-xs text-stone-400 md:col-span-5">Last dispatched: {{ $schedule['last_dispatched_at'] }}</p>@endif
                </div>
            @empty
                <p class="rounded-lg bg-stone-50 p-4 text-sm text-stone-500">No schedules. This source can still be run manually.</p>
            @endforelse
        </div>
    </section>

    <section class="grid gap-4 rounded-xl border bg-white p-5 xl:grid-cols-2">
        <label><span class="text-sm font-medium">Encrypted credentials JSON</span><textarea wire:model="credentialsJson" rows="12" class="mt-1 w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">Encrypted by Laravel at rest. Do not put credentials in Git.</span></label>
        <label><span class="text-sm font-medium">Settings JSON</span><textarea wire:model="settingsJson" rows="12" class="mt-1 w-full rounded border p-3 font-mono text-xs"></textarea></label>
        <label><span class="text-sm font-medium">Field mapping JSON</span><textarea wire:model="mappingJson" rows="12" class="mt-1 w-full rounded border p-3 font-mono text-xs"></textarea></label>
        <label><span class="text-sm font-medium">Capabilities JSON</span><textarea wire:model="capabilitiesJson" rows="12" class="mt-1 w-full rounded border p-3 font-mono text-xs"></textarea></label>
    </section>

    <button class="rounded-lg bg-lime-400 px-5 py-2.5 font-semibold text-stone-950">Save source & schedules</button>
</form>
