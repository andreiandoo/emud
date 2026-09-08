<form wire:submit="save" class="card-padded max-w-3xl space-y-5">
    @if($saved)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="field-label">Email de contact</span>
            <input type="email" wire:model="contact_email">
            @error('contact_email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Telefon</span>
            <input type="tel" wire:model="contact_phone">
            @error('contact_phone') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <div class="border-t border-stone-100 pt-5">
        <span class="field-label">Program de funcționare</span>

        <div class="mt-2 space-y-2">
            @foreach($opening_hours as $index => $row)
                <div class="grid items-center gap-3 sm:grid-cols-[8rem_1fr_1fr_7rem]">
                    <span class="text-sm font-medium">{{ $row['day'] }}</span>

                    <input type="time" wire:model="opening_hours.{{ $index }}.from"
                           @disabled($opening_hours[$index]['closed'])>
                    <input type="time" wire:model="opening_hours.{{ $index }}.to"
                           @disabled($opening_hours[$index]['closed'])>

                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" wire:model.live="opening_hours.{{ $index }}.closed">
                        Închis
                    </label>
                </div>
                @error('opening_hours.'.$index.'.from') <span class="field-error">{{ $message }}</span> @enderror
                @error('opening_hours.'.$index.'.to') <span class="field-error">{{ $message }}</span> @enderror
            @endforeach
        </div>

        {{-- A closed day stores no hours, so nothing downstream has to decide which of two
             answers to believe. --}}
        <span class="field-hint">Zilele marcate „Închis” nu păstrează ore.</span>
    </div>

    <button type="submit" class="btn-primary">Salvează</button>
</form>
