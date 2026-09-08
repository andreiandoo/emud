<div>
    <x-admin.page-header title="Bibliotecă media" subtitle="Imagini reutilizabile pentru conținut și catalog." />

    <form wire:submit="upload" class="mb-6 flex flex-wrap items-end gap-4 rounded-xl bg-stone-100 p-5">
        <label class="min-w-64 flex-1">
            <span class="field-label">Fișiere</span>
            <input type="file" multiple accept="image/*" wire:model="files">
            @error('files.*') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <button type="submit" class="btn-primary">Încarcă</button>
    </form>

    @if($assets->isEmpty())
        <x-admin.empty title="Biblioteca este goală" hint="Încarcă imagini ca să le poți refolosi în articole și pe produse." />
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
            @foreach($assets as $asset)
                <figure class="group overflow-hidden rounded-xl border border-stone-200 bg-white" wire:key="asset-{{ $asset->id }}">
                    <img src="{{ Storage::disk($asset->disk)->url($asset->path) }}" alt="{{ $asset->filename }}"
                         class="aspect-square w-full bg-stone-100 object-cover">

                    <figcaption class="flex items-center justify-between gap-2 p-2.5">
                        <span class="truncate text-xs text-stone-600" title="{{ $asset->path }}">{{ $asset->filename }}</span>

                        {{-- Kept visible rather than shown on hover: this list is also used on
                             touch screens, where there is no hover to reveal it. --}}
                        <button type="button" wire:click="delete({{ $asset->id }})"
                                wire:confirm="Ștergi fișierul din bibliotecă?"
                                class="shrink-0 rounded-lg px-1.5 py-0.5 text-xs font-semibold text-red-700 transition hover:bg-red-50"
                                aria-label="Șterge {{ $asset->filename }}">Șterge</button>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
</div>
