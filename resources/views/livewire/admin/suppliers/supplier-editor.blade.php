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
        <h2 class="font-bold">Qualification &amp; pipeline</h2>
        <p class="mt-1 text-xs text-stone-500">The commercial relationship has its own lifecycle. A supplier can sit here for months with no credentials; this is independent of whether the feed is allowed to run.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="space-y-1"><span class="text-sm">Supplier type</span><select wire:model="supplierType" class="w-full rounded border px-3 py-2">@foreach($supplierTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm">Onboarding status</span><select wire:model="onboardingStatus" class="w-full rounded border px-3 py-2">@foreach($onboardingStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm">Strategic role</span><select wire:model="strategicRole" class="w-full rounded border px-3 py-2"><option value="">—</option>@foreach($strategicRoles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm">Country code</span><input wire:model="countryCode" maxlength="2" class="w-full rounded border px-3 py-2" placeholder="RO"></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm">Website</span><input wire:model="website" class="w-full rounded border px-3 py-2" placeholder="https://"></label>
            <label class="space-y-1"><span class="text-sm">Qualification score (0–100)</span><input wire:model="qualificationScore" type="number" min="0" max="100" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Integration readiness (1–5)</span><input wire:model="readinessScore" type="number" min="1" max="5" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">4×4 fit score (0–100)</span><input wire:model="offroadFitScore" type="number" min="0" max="100" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1 md:col-span-3"><span class="text-sm">Next action</span><input wire:model="nextAction" class="w-full rounded border px-3 py-2" placeholder="What must happen before this supplier can move forward"></label>
            <label class="space-y-1 md:col-span-3"><span class="text-sm">Onboarding notes</span><textarea wire:model="onboardingNotes" rows="4" class="w-full rounded border p-3"></textarea></label>
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="font-bold">Capabilities</h2>
        <p class="mt-1 text-xs text-stone-500">Never assume every supplier reaches the same integration level. <strong>Unknown</strong> means the question has not been answered yet and is not the same as <strong>No</strong>. Only <strong>Yes</strong> lets the application call that capability.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($capabilityOptions as $capability)
                <label class="space-y-1 rounded border p-3">
                    <span class="block text-sm font-medium">{{ $capability->label() }}</span>
                    <select wire:model="capabilities.{{ $capability->value }}" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">Necunoscut</option><option value="yes">Da</option><option value="no">Nu</option>
                    </select>
                </label>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="font-bold">Commercial terms</h2>
        <p class="mt-1 text-xs text-stone-500">These values feed landed cost and supplier routing. Leave a field empty until the supplier has confirmed it in writing — an empty field is honest, a guessed one corrupts every margin calculation downstream.</p>

        <h3 class="mt-4 text-sm font-semibold text-stone-700">Fulfilment branding</h3>
        <p class="mt-1 text-xs text-stone-500">Dropshipping is not the same as blind dropshipping. White-label fulfilment counts as available only when blind shipping is Yes <em>and</em> supplier invoice in parcel is No.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['blind_shipping'=>'Blind shipping','neutral_packaging'=>'Neutral packaging','merchant_as_sender'=>'Merchant as sender','supplier_invoice_in_parcel'=>'Supplier invoice in parcel'] as $field=>$label)
                <label class="space-y-1 rounded border p-3">
                    <span class="block text-sm font-medium">{{ $label }}</span>
                    <select wire:model="fulfilment.{{ $field }}" class="w-full rounded border px-2 py-1.5 text-sm">
                        <option value="">Necunoscut</option><option value="yes">Da</option><option value="no">Nu</option>
                    </select>
                </label>
            @endforeach
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <label class="space-y-1"><span class="text-sm">Dropship fee / order</span><input wire:model="dropshipFee" class="w-full rounded border px-3 py-2" placeholder="empty = unknown"></label>
            <label class="space-y-1"><span class="text-sm">Packaging / handling fee</span><input wire:model="packagingFee" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Terms currency</span><input wire:model="termsCurrency" maxlength="3" class="w-full rounded border px-3 py-2" placeholder="EUR"></label>
            <label class="space-y-1"><span class="text-sm">Minimum order value</span><input wire:model="minimumOrderValue" class="w-full rounded border px-3 py-2" placeholder="0 = no minimum"></label>
            <label class="space-y-1"><span class="text-sm">Free shipping threshold</span><input wire:model="freeShippingThreshold" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Payment terms (days)</span><input wire:model="paymentTermsDays" type="number" min="0" max="365" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Return window (days)</span><input wire:model="returnWindowDays" type="number" min="0" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Restocking fee (%)</span><input wire:model="restockingFeePercent" class="w-full rounded border px-3 py-2"></label>
            <label class="space-y-1"><span class="text-sm">Return freight paid by</span><select wire:model="returnFreightPayer" class="w-full rounded border px-3 py-2"><option value="">Necunoscut</option><option value="reseller">Revânzător</option><option value="supplier">Furnizor</option><option value="case_by_case">De la caz la caz</option><option value="unknown">Declarat necunoscut</option></select></label>
            <label class="space-y-1"><span class="text-sm">Allowed destinations</span><input wire:model="allowedCountries" class="w-full rounded border px-3 py-2" placeholder="Empty = unrestricted. e.g. RO, BG, HU"></label>
            <label class="space-y-1"><span class="text-sm">Excluded destinations</span><input wire:model="excludedCountries" class="w-full rounded border px-3 py-2" placeholder="e.g. UA, RS"></label>
            <label class="space-y-1"><span class="text-sm">MAP / advertised price policy</span><input wire:model="mapPolicy" class="w-full rounded border px-3 py-2"></label>
        </div>
    </section>

    @php($unresolved = data_get($supplier, 'commercial_profile.unresolved', []))
    @php($researchSources = data_get($supplier, 'commercial_profile.sources', []))
    @if($unresolved !== [])
        <section class="rounded-xl border border-sky-200 bg-sky-50 p-5">
            <h2 class="font-bold text-sky-950">Unresolved with this supplier</h2>
            <p class="mt-1 text-xs text-sky-900">Open questions recorded during public research. Answer these in writing before building an adapter.</p>
            <ul class="mt-3 flex flex-wrap gap-2">@foreach($unresolved as $item)<li class="rounded border border-sky-200 bg-white px-2 py-1 text-xs text-sky-900">{{ $item }}</li>@endforeach</ul>
            @if($researchSources !== [])
                <div class="mt-3 flex flex-wrap gap-3 text-xs">@foreach($researchSources as $source)<a href="{{ $source }}" target="_blank" rel="noopener" class="text-sky-800 underline">{{ parse_url($source, PHP_URL_HOST) }}</a>@endforeach</div>
            @endif
        </section>
    @endif

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

    <section class="rounded-xl border bg-white p-5">
        <h2 class="font-bold">Technical catalog promotion</h2>
        <p class="mt-1 text-xs text-stone-500">Promotes manufacturer identifiers, OE/IAM references, attributes, fitments, cross-references and supersessions into the technical catalog. Promotion is still blocked unless Derived data rights are enabled.</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <label class="flex items-start gap-3 rounded-lg border p-4">
                <input type="checkbox" wire:model="technicalPromotionEnabled" class="mt-1">
                <span><strong class="block text-sm">Enable technical promotion</strong><span class="mt-1 block text-xs text-stone-500">Stages supplier technical fields through the canonical assertion/provenance pipeline after catalog matching.</span></span>
            </label>
            <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <input type="checkbox" wire:model="technicalPromotionCreateParts" class="mt-1">
                <span><strong class="block text-sm text-amber-950">Allow creation of new canonical parts</strong><span class="mt-1 block text-xs text-amber-900">Stronger mode. When disabled, supplier data can only augment an existing exact brand+MPN identity.</span></span>
            </label>
        </div>
        @if($technicalPromotionEnabled && !$allowDerived)
            <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-800">Technical promotion is configured but will remain blocked because Derived data rights are disabled.</div>
        @endif
    </section>

    <section class="grid gap-4 rounded-xl border bg-white p-5 xl:grid-cols-3">
        <label class="space-y-1"><span class="text-sm font-medium">Encrypted credentials JSON</span><textarea wire:model="credentialsJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">Stored encrypted by Laravel. For SFTP use host, username, host_fingerprint and password/private_key; never commit real credentials.</span></label>
        <label class="space-y-1"><span class="text-sm font-medium">Settings JSON</span><textarea wire:model="settingsJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">The promotion switches above are persisted into this settings object on save.</span></label>
        <label class="space-y-1"><span class="text-sm font-medium">Field mapping JSON</span><textarea wire:model="mappingJson" rows="15" class="w-full rounded border p-3 font-mono text-xs"></textarea><span class="text-xs text-stone-500">Map actual vendor export headers only after inspecting a sample file. Technical keys include oe_numbers, iam_numbers, cross_references, supersessions, attributes and fitments.</span></label>
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
