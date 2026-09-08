<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Furnizori</h1><p class="text-stone-500">Pipeline comercial, capabilități, drepturi și controlul feedurilor.</p></div>
        <a href="{{ route('admin.suppliers.create') }}" class="rounded-lg bg-lime-400 px-4 py-2 text-sm font-semibold text-stone-950">Adaugă furnizor</a>
    </div>

    @if(session('status'))<div class="mb-4 rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>@endif

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach($statuses as $pipelineStatus)
            @php($count = $pipeline[$pipelineStatus->value] ?? 0)
            <button wire:click="$set('status', '{{ $status === $pipelineStatus->value ? '' : $pipelineStatus->value }}')"
                @class([
                    'rounded-lg border px-3 py-1.5 text-xs',
                    'border-stone-900 bg-stone-900 text-white' => $status === $pipelineStatus->value,
                    'border-stone-200 bg-white text-stone-600 hover:bg-stone-50' => $status !== $pipelineStatus->value,
                    'opacity-50' => $count === 0,
                ])>{{ $pipelineStatus->label() }} <span class="font-bold">{{ $count }}</span></button>
        @endforeach
    </div>

    <div class="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <label class="space-y-1"><span class="text-xs text-stone-500">Caută</span><input wire:model.live.debounce.300ms="search" class="w-full rounded border px-3 py-2 text-sm" placeholder="Nume sau cod"></label>
        <label class="space-y-1"><span class="text-xs text-stone-500">Rol strategic</span><select wire:model.live="role" class="w-full rounded border px-3 py-2 text-sm"><option value="">Toate</option>@foreach($roles as $strategicRole)<option value="{{ $strategicRole->value }}">{{ $strategicRole->label() }}</option>@endforeach</select></label>
        <label class="space-y-1"><span class="text-xs text-stone-500">Țară</span><select wire:model.live="country" class="w-full rounded border px-3 py-2 text-sm"><option value="">Toate</option>@foreach($countries as $countryCode)<option value="{{ $countryCode }}">{{ $countryCode }}</option>@endforeach</select></label>
        <button type="button" wire:click="resetFilters" class="self-end rounded border px-3 py-2 text-sm hover:bg-stone-50">Resetează filtrele</button>
    </div>

    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-stone-500">Feeduri configurate</h2>
    <div class="grid gap-4 xl:grid-cols-2">
    @forelse($configured as $supplier)
        <section class="rounded-xl border bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-bold">{{ $supplier->name }}</h3>
                        <span class="rounded bg-stone-100 px-2 py-0.5 text-[11px] text-stone-600">{{ $supplier->data_rights_class->value }}</span>
                        @if($supplier->onboarding_status)<span class="rounded bg-sky-100 px-2 py-0.5 text-[11px] text-sky-900">{{ $supplier->onboarding_status->label() }}</span>@endif
                    </div>
                    <div class="mt-1 text-sm text-stone-500">{{ $supplier->code }} · {{ $supplier->protocol->value }} · {{ $supplier->products_count }} repere @if($supplier->country_code)· {{ $supplier->country_code }}@endif</div>
                </div>
                <button wire:click="toggle({{ $supplier->id }})" @class(['rounded-full px-3 py-1 text-xs font-bold','bg-lime-100 text-lime-800'=>$supplier->is_active,'bg-stone-200 text-stone-600'=>!$supplier->is_active])>{{ $supplier->is_active ? 'activ' : 'oprit' }}</button>
            </div>
            <div class="mt-4 grid gap-2 text-xs text-stone-500 sm:grid-cols-2">
                <div>Ultimul succes: {{ $supplier->last_successful_sync_at?->diffForHumans() ?? 'niciodată' }}</div>
                <div>API redistribution: <strong>{{ $supplier->allow_api_redistribution ? 'permis' : 'blocat' }}</strong></div>
                <div>Dropship neutru confirmat: <strong>{{ $supplier->hasConfirmedBlindFulfilment() ? 'da' : 'nu' }}</strong></div>
                <div>Comandă automată: <strong>{{ $supplier->supports_order_api === true ? 'da' : 'nu' }}</strong></div>
            </div>
            @if($supplier->syncSchedules->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($supplier->syncSchedules as $schedule)
                        <span @class(['rounded border px-2 py-1 font-mono text-[11px]','border-lime-200 bg-lime-50 text-lime-900'=>$schedule->is_enabled,'border-stone-200 bg-stone-50 text-stone-500'=>!$schedule->is_enabled])>{{ $schedule->mode }}: {{ $schedule->cron_expression }} · {{ $schedule->timezone }}</span>
                    @endforeach
                </div>
            @endif
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="rounded-lg bg-stone-900 px-3 py-2 text-sm font-medium text-white">Configurează</a>
                @foreach(['catalog'=>'Catalog','stock'=>'Stoc','prices'=>'Prețuri'] as $mode=>$label)<button wire:click="sync({{ $supplier->id }}, '{{ $mode }}')" wire:loading.attr="disabled" class="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-stone-50">Sincronizează {{ $label }}</button>@endforeach
            </div>
        </section>
    @empty
        <div class="rounded-xl border bg-white p-8 text-stone-500">Niciun feed configurat încă. Un furnizor ajunge aici după ce ajunge cel puțin la „Mostră primită”.</div>
    @endforelse
    </div>

    <h2 class="mt-10 mb-3 text-sm font-bold uppercase tracking-wide text-stone-500">Pipeline comercial <span class="font-normal normal-case text-stone-400">— {{ $prospects->count() }} candidați, ordonați după scorul de calificare</span></h2>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="border-b bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="p-3">Furnizor</th><th class="p-3">Țară</th><th class="p-3">Rol</th>
                    <th class="p-3 text-right">Scor</th><th class="p-3 text-right">4×4</th>
                    <th class="p-3">Status</th><th class="p-3">Următoarea acțiune</th><th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
            @forelse($prospects as $supplier)
                <tr class="align-top">
                    <td class="p-3">
                        <div class="font-semibold">{{ $supplier->name }}</div>
                        <div class="text-xs text-stone-500">{{ $supplier->code }} · {{ $supplier->protocol->value }}</div>
                    </td>
                    <td class="p-3 text-stone-600">{{ $supplier->country_code ?? '—' }}</td>
                    <td class="p-3 text-xs text-stone-600">{{ $supplier->strategic_role?->label() ?? '—' }}</td>
                    <td @class(['p-3 text-right font-bold','text-lime-700'=>$supplier->qualification_score >= 85,'text-stone-700'=>$supplier->qualification_score >= 65 && $supplier->qualification_score < 85,'text-red-700'=>$supplier->qualification_score !== null && $supplier->qualification_score < 65])>{{ $supplier->qualification_score ?? '—' }}</td>
                    <td class="p-3 text-right text-stone-600">{{ $supplier->offroad_fit_score ?? '—' }}</td>
                    <td class="p-3"><span class="rounded bg-stone-100 px-2 py-0.5 text-[11px] text-stone-700">{{ $supplier->onboarding_status?->label() }}</span></td>
                    <td class="max-w-md p-3 text-xs text-stone-600">{{ $supplier->next_action }}</td>
                    <td class="p-3 text-right"><a href="{{ route('admin.suppliers.edit', $supplier) }}" class="rounded border px-2 py-1 text-xs hover:bg-stone-50">Deschide</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-8 text-center text-stone-500">Niciun candidat pentru filtrele curente.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
