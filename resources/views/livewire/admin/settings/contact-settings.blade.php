<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Date de contact" subtitle="Afișate în footer, pe pagina de contact și în emailurile tranzacționale.">
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

        <x-admin.section title="Program de funcționare">
            <div class="space-y-2">
                @foreach($opening_hours as $index => $row)
                    <div class="grid items-center gap-3 rounded-lg bg-stone-50 px-4 py-2.5 sm:grid-cols-[8rem_1fr_1fr_7rem]"
                         wire:key="hours-{{ $index }}">
                        <span class="text-sm font-medium text-stone-900">{{ $row['day'] }}</span>

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
        </x-admin.section>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
