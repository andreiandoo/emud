<div>
    <div class="mb-6"><h1 class="text-2xl font-bold">Furnizori</h1><p class="text-stone-500">Conectori, frecvențe și controlul feedurilor dropshipping.</p></div>
    @if(session('status'))<div class="mb-4 rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>@endif
    <div class="grid gap-4 xl:grid-cols-2">
    @forelse($suppliers as $supplier)
        <section class="rounded-xl border bg-white p-5"><div class="flex items-start justify-between"><div><h2 class="font-bold">{{ $supplier->name }}</h2><div class="text-sm text-stone-500">{{ $supplier->code }} · {{ $supplier->protocol->value }} · {{ $supplier->products_count }} repere</div></div><button wire:click="toggle({{ $supplier->id }})" @class(['rounded-full px-3 py-1 text-xs font-bold','bg-lime-100 text-lime-800'=>$supplier->is_active,'bg-stone-200 text-stone-600'=>!$supplier->is_active])>{{ $supplier->is_active ? 'activ' : 'oprit' }}</button></div>
            <div class="mt-4 text-xs text-stone-500">Ultimul succes: {{ $supplier->last_successful_sync_at?->diffForHumans() ?? 'niciodată' }}</div>
            <div class="mt-4 flex flex-wrap gap-2">@foreach(['catalog'=>'Catalog','stock'=>'Stoc','prices'=>'Prețuri'] as $mode=>$label)<button wire:click="sync({{ $supplier->id }}, '{{ $mode }}')" wire:loading.attr="disabled" class="rounded-lg border px-3 py-2 text-sm font-medium hover:bg-stone-50">Sincronizează {{ $label }}</button>@endforeach</div>
        </section>
    @empty <div class="rounded-xl border bg-white p-8 text-stone-500">Adaugă primul furnizor direct în baza de date sau printr-un seeder dedicat contractului de feed.</div> @endforelse
    </div>
</div>
