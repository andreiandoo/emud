<form wire:submit="save" class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.suppliers.index') }}" class="text-sm text-stone-500">← Suppliers</a>
            <h1 class="mt-2 text-2xl font-bold">{{ $supplier?->exists ? 'Edit supplier feed' : 'New supplier feed' }}</h1>
            <p class="mt-1 text-sm text-stone-500">Transport, rights, source mappings and independent synchronization schedules.</p>
        </div>
        @if($supplier?->settings['profile'] ?? null)
            <div class="rounded-lg border border-lime-200 bg-lime-50 px-4 py-2 text-sm text-lime-900">
                Profile: <strong>{{ $supplier->settings['profile'] }}</strong>
            </div>
        @endif
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="grid gap-4 rounded-xl border bg-white p-5 md:grid-cols-2">
        <label class="space-y-1"><span class="text-sm font-medium">Name</span><input wire:model="name" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Code</span><input wire:model="code" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Protocol</span><select wire:model="protocol" class="w-full rounded border px-3 py-2">@foreach($protocols as $item)<option value="{{ $item->value }}">{{ $item->value }}</option>@endforeach</select></label>
        <label class="space-y-1"><span class="text-sm font-medium">Connector class</span><input wire:model="connectorClass" class="w-full rounded border px-3 py-2" placeholder="Leave blank for protocol default"></label>
        <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Catalog / material-master path</span><input wire:model="catalogEndpoint" class="w-full rounded border px-3 py-2" placeholder="Remote path, URL or wildcard supplied by the vendor"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Price path</span><input wire:model="priceEndpoint" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Stock path</span><input wire:model="stockEndpoint" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Default currency</span><input wire:model="defaultCurrency" maxlength="3" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Supplier timezone</span><input wire:model="timezone" class="w-full rounded border px-3 py-2"></label>
        <label class="space-y-1"><span class="text-sm font-medium">Priority</span><input wire:model="priority" type="number" min="0" max="65535" class="w-full rounded border px-3 py-2"></label>
        <label class="flex items-center gap-2 self-end rounded border p-3"><input type="checkbox" wire:model="isActive"><span class="text-sm font-medium">Supplier active</span></label>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="font-bold">Data rights</h2>
        <p class="mt-1 text-xs text-stone-500">These flags are enforcement inputs. Do not enable broader rights merely because the feed is technically accessible.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm">Rights class</span><select wire:model="rightsClass" class="w-full rounded border px-3 py-2">@foreach($rightsClasses as $rights)<option value="{{ $rights->value }}">{{ $rights->value }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm">License / agreement name</span><input wire:model="licenseName" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm">License / documentation URL</span><input wire:model="licenseUrl" class="w-full rounded border px-3 py-2"></label>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @foreach(['allowInternal'=>'Internal ingestion','allowEcommerce'=>'Ecommerce','allowDerived'=>'Derived data','allowApiRedistribution'=>'Public API','attributionRequired'=>'Attribution'] as $property=>$label)
                <label class="flex items-center gap-2 rounded border p-3"><input type="checkbox" wire:model="{{ $property }}"><span class="text-sm">{{ $label }}</span></label>
            @endforeach
        </div>
        <label class="mt-4 block space-y-1"><span class="text-sm">Legal notes</span><textarea wire:model="legalNotes" rows="5" class="w-full rounded border p-3"></textarea></label>
    </section>

    <section class="grid gap-4 rounded-xl border bg-white p-5 xl:grid-cols-3">
        <label class="space-y-1"><span class="text-sm font-medium">Encrypted credentials JSON</span><textarea wire:model="credentialsJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">Stored encrypted by Laravel. For SFTP use host, username, host_fingerprint and password/private_key; never commit real credentials.</span></label>
        <label class="space-y-1"><span class="text-sm font-medium">Settings JSON</span><textarea wire:model="settingsJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea></label>
        <label class="space-y-1"><span class="text-sm font-medium">Field mapping JSON</span><textarea wire:model="mappingJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">Map actual vendor export headers only after inspecting a sample file.</span></label>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <div><h2 class="font-bold">Per-supplier schedules</h2><p class="mt-1 text-xs text-stone-500">Enabled rows override the legacy global cadence for that supplier and mode.</p></div>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach(['catalog'=>'Catalog / material master','prices'=>'Prices','stock'=>'Stock / availability'] as $mode=>$label)
                <div class="rounded-lg border p-4">
                    <div class="flex items-center justify-between gap-3"><strong class="text-sm">{{ $label }}</strong><label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="schedules.{{ $mode }}.enabled">Enabled</label></div>
                    <label class="mt-3 block space-y-1"><span class="text-xs text-stone-500">Cron expression</span><input wire:model="schedules.{{ $mode }}.cron" class="w-full rounded border px-3 py-2 font-mono text-sm"></label>
                    <label class="mt-3 block space-y-1"><span class="text-xs text-stone-500">Timezone</span><input wire:model="schedules.{{ $mode }}.timezone" class="w-full rounded border px-3 py-2 text-sm"></label>
                </div>
            @endforeach
        </div>
    </section>

    @if(($supplier?->settings['required_setup'] ?? []) !== [])
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-bold text-amber-950">Profile activation checklist</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-amber-950">@foreach($supplier->settings['required_setup'] as $step)<li>{{ $step }}</li>@endforeach</ol>
        </section>
    @endif

    <button class="rounded-lg bg-lime-400 px-5 py-2.5 font-semibold text-stone-950">Save supplier</button>
</form>
