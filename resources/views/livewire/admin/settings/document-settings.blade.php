<form wire:submit="save" class="card-padded max-w-3xl space-y-5">
    @if($saved)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
    @endif

    <p class="text-sm text-stone-600">
        Prefixul și următorul număr pentru fiecare tip de document.
    </p>

    <div class="space-y-4">
        @foreach($labels as $key => $label)
            <div class="grid items-end gap-4 sm:grid-cols-[10rem_1fr_1fr_auto]">
                <span class="text-sm font-medium">{{ $label }}</span>

                <label class="block">
                    <span class="field-label">Prefix</span>
                    <input wire:model="series.{{ $key }}.prefix">
                    @error('series.'.$key.'.prefix') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Următorul număr</span>
                    <input type="number" min="1" wire:model="series.{{ $key }}.next">
                    @error('series.'.$key.'.next') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <span class="pb-2 text-sm text-stone-500">
                    {{ ($series[$key]['prefix'] ?? '') }}-{{ str_pad((string) ($series[$key]['next'] ?? 1), 5, '0', STR_PAD_LEFT) }}
                </span>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-stone-500">
        Numărul următor este păstrat explicit, nu dedus dintr-un total: un total ar reutiliza un
        număr după ștergerea unui document, iar reemiterea unui număr deja dat unui client este o
        problemă fiscală, nu una de afișare.
    </p>

    <button type="submit" class="btn-primary">Salvează</button>
</form>
