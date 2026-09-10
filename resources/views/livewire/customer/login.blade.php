<x-storefront.auth title="Autentificare" intro="Intră în cont ca să îți accesezi garajul și comenzile.">
    @if(session('status'))
        <p class="mb-6 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
            <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> {{ session('status') }}
        </p>
    @endif

    <form wire:submit="authenticate" class="grid gap-5">
        <label class="block">
            <span class="field-label">Email</span>
            <input type="email" wire:model="email" autocomplete="email">
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Parolă</span>
            <input type="password" wire:model="password" autocomplete="current-password">
            @error('password') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-ink2">
                <input type="checkbox" wire:model="remember">
                Ține-mă minte
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-ink2 underline underline-offset-2 hover:text-ink">Am uitat parola</a>
        </div>

        <button type="submit" class="st-btn st-btn--block">
            <span wire:loading.remove wire:target="authenticate">Intră în cont</span>
            <span wire:loading wire:target="authenticate">Se verifică…</span>
        </button>
    </form>

    <p class="mt-8 border-t border-line pt-6 text-sm text-ink2">
        Nu ai cont? <a href="{{ route('customer.register') }}" class="font-semibold text-ink underline underline-offset-2">Creează unul</a>
    </p>
</x-storefront.auth>
