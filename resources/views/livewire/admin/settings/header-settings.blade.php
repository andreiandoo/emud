<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Barele din header" subtitle="Ce se vede deasupra și sub headerul magazinului.">
        @if($saved)
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
        @endif

        <x-admin.section title="Bara de sus">
            <label class="block">
                <span class="field-label">Mesaj</span>
                <input wire:model="topbar_message" placeholder="Transport gratuit la comenzi peste 500 lei">
                <span class="field-hint">Lăsat gol, bara afișează doar telefonul și linkul către service-uri.</span>
                @error('topbar_message') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">Text link</span>
                    <input wire:model="topbar_link_label" placeholder="Vezi condițiile">
                    @error('topbar_link_label') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Adresă link</span>
                    <input wire:model="topbar_link_url" placeholder="/livrare">
                    @error('topbar_link_url') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>
        </x-admin.section>

        <x-admin.section title="Banda de sub header">
            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" wire:model.live="promo_enabled">
                Afișează banda
            </label>

            <label class="block">
                <span class="field-label">Mesaj</span>
                <input wire:model="promo_message" @disabled(! $promo_enabled) placeholder="Reduceri de sezon la suspensii — până duminică">
                @error('promo_message') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">Text link</span>
                    <input wire:model="promo_link_label" @disabled(! $promo_enabled) placeholder="Vezi ofertele">
                    @error('promo_link_label') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Adresă link</span>
                    <input wire:model="promo_link_url" @disabled(! $promo_enabled) placeholder="/categorie/suspensii">
                    @error('promo_link_url') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            {{-- Said here because it is the non-obvious half of a dismissible bar: the visitor
                 who closed the last campaign is exactly the one who must see the next. --}}
            <p class="field-hint">
                Banda poate fi închisă de vizitator. Când schimbi mesajul, ea reapare tuturor —
                inclusiv celor care o închiseseră.
            </p>
        </x-admin.section>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
