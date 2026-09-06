<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Furnizori</h1><p class="text-stone-500">Conectori, drepturi, frecvențe și controlul feedurilor comerciale.</p></div>
        <a href="{{ route('admin.suppliers.create') }}" class="rounded-lg bg-lime-400 px-4 py-2 text-sm font-semibold text-stone-950">Adaugă furnizor</a>
    </div>
    @if(session('status'))<div class="mb-4 rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>@endif
    <div class="grid gap-4 xl:grid-cols-2">
    @forelse($suppliers as $supplier)
        <section class="rounded-xl border bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><h2 class="font-bold">{{ $supplier->name }}</h2><span class="rounded bg-stone-100 px-2 py-0.5 text-[11px] text-stone-600">{{ $supplier->data_rights_class->value }}</span></div>
                    <div class="mt-1 text-sm text-stone-500">{{ $supplier->code }} · {{ $supplier->protocol->value }} · {{ $supplier->products_count }} repere</div>
                </div>
                <button wire:click="toggle({{ $supplier->id }})" @class(['rounded-full px-3 py-1 text-xs font-bold','bg-lime-100 text-lime-800'=>$supplier->is_active,'bg-stone-200 text-stone-600'=>!$supplier->is_active])>{{ $supplier->is_active ? 'activ' : 'oprit' }}</button>
            </div>
            <div class="mt-4 grid gap-2 text-xs text-stone-500 sm:grid-cols-2">
                <div>Ultimul succes: {{ $supplier->last_successful_sync_at?->diffForHumans() ?? 'niciodată' }}</div>
                <div>API redistribution: <strong>{{ $supplier->allow_api_redistribution ? 'permis' : 'blocat' }}</strong></div>
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
        <div class="rounded-xl border bg-white p-8 text-stone-500">Nu există furnizori configurați. Poți adăuga unul din interfață sau prin profilurile seeduite.</div>
    @endforelse
    </div>
</div>
