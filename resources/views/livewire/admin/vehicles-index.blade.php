<div>
    <div class="mb-6"><h1 class="text-2xl font-bold">Compatibilitate auto</h1><p class="text-stone-500">Marcă → model → generație → motor/configurație, plus reguli de fitment.</p></div>
    <input wire:model.live.debounce.300ms="search" placeholder="Caută marca" class="mb-4 w-full max-w-md rounded-lg border bg-white px-3 py-2">
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($makes as $make)
            <section class="rounded-xl border bg-white p-5"><div class="mb-3 flex items-center justify-between"><h2 class="font-bold">{{ $make->name }}</h2><span class="rounded-full bg-stone-100 px-2 py-1 text-xs">{{ $make->models_count }} modele</span></div>
                <div class="divide-y">@foreach($make->models as $model)<div class="flex justify-between py-2 text-sm"><span>{{ $model->name }}</span><span class="text-stone-400">{{ $model->generations_count }} generații</span></div>@endforeach</div>
            </section>
        @empty <div class="text-stone-500">Baza auto este pregătită pentru import; nu există încă date.</div> @endforelse
    </div>
</div>
