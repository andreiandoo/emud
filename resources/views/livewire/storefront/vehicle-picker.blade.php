<div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-stone-500">Alege mașina</h2>
        @if($this->makeId)
            <button type="button" wire:click="clear" class="text-xs text-stone-500 underline hover:text-stone-900">Renunță la mașină</button>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Marcă</span>
            <select wire:model.live="makeId" class="w-full rounded-lg border-stone-300 text-sm">
                <option value="">Selectează marca</option>
                @foreach($makes as $make)
                    <option value="{{ $make->id }}">{{ $make->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Model</span>
            <select wire:model.live="modelId" @disabled($models->isEmpty()) class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                <option value="">{{ $models->isEmpty() ? 'Alege întâi marca' : 'Selectează modelul' }}</option>
                @foreach($models as $model)
                    <option value="{{ $model->id }}">{{ $model->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Generație <span class="font-normal text-stone-400">(opțional)</span></span>
            <select wire:model.live="generationId" @disabled($generations->isEmpty()) class="w-full rounded-lg border-stone-300 text-sm disabled:bg-stone-100">
                <option value="">{{ $generations->isEmpty() ? 'Alege întâi modelul' : 'Toate generațiile' }}</option>
                @foreach($generations as $generation)
                    <option value="{{ $generation->id }}">{{ $generation->name }} ({{ $generation->year_from }}{{ $generation->year_to ? '–'.$generation->year_to : '+' }})</option>
                @endforeach
            </select>
        </label>
    </div>

    @error('makeId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
    @error('modelId') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

    <button type="button" wire:click="apply" @disabled(! $this->modelId)
            class="mt-4 w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700 disabled:cursor-not-allowed disabled:bg-stone-300 sm:w-auto">
        Caută piese pentru mașina mea
    </button>
</div>
