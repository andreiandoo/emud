<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Serii de documente" subtitle="Prefixul și următorul număr pentru fiecare tip de document.">
        @if($saved)
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
        @endif

        <div class="space-y-3">
            @foreach($labels as $key => $label)
                <div class="grid items-end gap-4 rounded-xl bg-stone-50 p-4 sm:grid-cols-[10rem_1fr_1fr_auto]"
                     wire:key="series-{{ $key }}">
                    <span class="pb-2 text-sm font-medium text-stone-900">{{ $label }}</span>

                    <label class="block">
                        <span class="field-label">Prefix</span>
                        <input wire:model.live.debounce.400ms="series.{{ $key }}.prefix">
                        @error('series.'.$key.'.prefix') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Următorul număr</span>
                        <input type="number" min="1" wire:model.live.debounce.400ms="series.{{ $key }}.next">
                        @error('series.'.$key.'.next') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    {{-- The rendered result, so the operator can see what the next document will
                         actually be called before saving. --}}
                    <span class="pb-2 font-mono text-sm text-stone-500">
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

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
