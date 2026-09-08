<div>
    <x-admin.page-header title="Compatibilitate auto" subtitle="Marcă → model → generație → motor/configurație, plus reguli de fitment." />

    <label class="relative mb-6 block max-w-md">
        <span class="sr-only">Caută marcă</span>
        <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Caută marca">
    </label>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($makes as $make)
            <x-admin.panel :title="$make->name" :subtitle="trans_choice(':count model|:count modele', $make->models_count, ['count' => $make->models_count])"
                           wire:key="make-{{ $make->id }}">
                @if($make->models->isEmpty())
                    <p class="text-sm text-stone-500">Niciun model importat pentru această marcă.</p>
                @else
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach($make->models as $model)
                            <li class="flex items-baseline justify-between gap-3 py-2">
                                <span class="text-stone-800">{{ $model->name }}</span>
                                <span class="shrink-0 tabular-nums text-stone-400">{{ $model->generations_count }} generații</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.panel>
        @empty
            <div class="lg:col-span-2">
                <x-admin.empty title="Baza auto este goală"
                               hint="Structura este pregătită pentru import; rulează o sursă de catalog ca să apară mărcile." />
            </div>
        @endforelse
    </div>
</div>
