<div>
    <x-admin.page-header title="Compatibilitate auto" subtitle="Marcă → model → generație → motor/configurație, plus reguli de fitment." />
    <input wire:model.live.debounce.300ms="search" placeholder="Caută marca" class="mb-4 w-full max-w-md rounded-lg border bg-white px-3 py-2">
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($makes as $make)
            <section class="card-padded"><div class="mb-3 flex items-center justify-between"><h2 class="font-semibold">{{ $make->name }}</h2><span class="rounded-full bg-stone-100 px-2 py-1 text-xs">{{ $make->models_count }} modele</span></div>
                <div class="divide-y">@foreach($make->models as $model)<div class="flex justify-between py-2 text-sm"><span>{{ $model->name }}</span><span class="text-stone-400">{{ $model->generations_count }} generații</span></div>@endforeach</div>
            </section>
        @empty <div class="text-stone-500">Baza auto este pregătită pentru import; nu există încă date.</div> @endforelse
    </div>
</div>
