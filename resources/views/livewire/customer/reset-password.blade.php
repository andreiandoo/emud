<x-storefront.auth title="Setează o parolă nouă">
    <form wire:submit="resetPassword" class="grid gap-5">
        <label class="block">
            <span class="field-label">Email</span>
            <input type="email" wire:model="email" autocomplete="email">
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Parolă nouă</span>
            <input type="password" wire:model="password" autocomplete="new-password">
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Confirmă parola</span>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password">
        </label>

        <button type="submit" class="st-btn st-btn--block">
            <span wire:loading.remove wire:target="resetPassword">Schimbă parola</span>
            <span wire:loading wire:target="resetPassword">Se salvează…</span>
        </button>
    </form>
</x-storefront.auth>
