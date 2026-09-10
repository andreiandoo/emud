<x-storefront.auth title="Creează cont" intro="Salvează-ți mașinile în garaj și vezi doar piesele care li se potrivesc.">
    <form wire:submit="register" class="grid gap-5">
        <label class="block">
            <span class="field-label">Nume</span>
            <input type="text" wire:model="name" autocomplete="name">
            @error('name') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Email</span>
                <input type="email" wire:model="email" autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Telefon <span class="font-normal opacity-70">(opțional)</span></span>
                <input type="tel" wire:model="phone" autocomplete="tel">
                @error('phone') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Parolă</span>
                <input type="password" wire:model="password" autocomplete="new-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Confirmă parola</span>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password">
            </label>
        </div>

        <label class="flex items-start gap-2.5 text-sm text-ink2">
            <input type="checkbox" wire:model="marketing_consent" class="mt-0.5">
            <span>Vreau să primesc noutăți și oferte pe email. Mă pot dezabona oricând.</span>
        </label>

        <button type="submit" class="st-btn st-btn--block">
            <span wire:loading.remove wire:target="register">Creează contul</span>
            <span wire:loading wire:target="register">Se creează…</span>
        </button>
    </form>

    <p class="mt-8 border-t border-line pt-6 text-sm text-ink2">
        Ai deja cont? <a href="{{ route('customer.login') }}" class="font-semibold text-ink underline underline-offset-2">Autentifică-te</a>
    </p>
</x-storefront.auth>
