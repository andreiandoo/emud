<form wire:submit="save" class="card-padded max-w-3xl space-y-5">
    @if($saved)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
    @endif

    <p class="text-sm text-stone-600">
        Aceste date apar pe facturi și în termeni și condiții.
    </p>

    <label class="block">
        <span class="field-label">Denumire companie</span>
        <input wire:model="company_name">
        @error('company_name') <span class="field-error">{{ $message }}</span> @enderror
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
            <span class="field-label">CUI</span>
            <input wire:model="company_vat_id" placeholder="RO12345678">
            @error('company_vat_id') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Nr. Registrul Comerțului</span>
            <input wire:model="company_registration_number" placeholder="J12/345/2020">
            @error('company_registration_number') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" wire:model="company_vat_payer">
        Plătitor de TVA
    </label>

    <div class="grid gap-4 border-t border-stone-100 pt-5 sm:grid-cols-2">
        <label class="block">
            <span class="field-label">Bancă</span>
            <input wire:model="company_bank_name">
            @error('company_bank_name') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">IBAN</span>
            <input wire:model="company_iban" placeholder="RO49AAAA1B31007593840000">
            @error('company_iban') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <label class="block">
        <span class="field-label">Adresă</span>
        <input wire:model="company_address">
        @error('company_address') <span class="field-error">{{ $message }}</span> @enderror
    </label>

    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
            <span class="field-label">Oraș</span>
            <input wire:model="company_city">
        </label>
        <label class="block">
            <span class="field-label">Județ</span>
            <input wire:model="company_county">
        </label>
        <label class="block">
            <span class="field-label">Țară</span>
            <input wire:model="company_country">
            @error('company_country') <span class="field-error">{{ $message }}</span> @enderror
        </label>
    </div>

    <button type="submit" class="btn-primary">Salvează</button>
</form>
