<x-storefront.account active="profile" title="Datele mele" intro="Numele, adresa de email și parola contului.">
    @if($status)
        <p class="mb-8 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
            <x-storefront.icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $status }}
        </p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="saveProfile" class="grid content-start gap-5 rounded-[3px] bg-white p-6 sm:p-8">
            <h2 class="font-display text-2xl font-semibold">Profil</h2>

            <label class="block">
                <span class="field-label">Nume</span>
                <input type="text" wire:model="name" autocomplete="name">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Email</span>
                <input type="email" wire:model="email" autocomplete="email">
                @error('email') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Telefon</span>
                <input type="tel" wire:model="phone" autocomplete="tel">
            </label>
            <label class="flex items-start gap-2.5 text-sm text-ink2">
                <input type="checkbox" wire:model="marketing_consent" class="mt-0.5">
                <span>Vreau să primesc noutăți și oferte pe email.</span>
            </label>

            <div><button type="submit" class="st-btn st-btn--ink">Salvează</button></div>
        </form>

        <form wire:submit="changePassword" class="grid content-start gap-5 rounded-[3px] bg-white p-6 sm:p-8">
            <h2 class="font-display text-2xl font-semibold">Schimbă parola</h2>

            <label class="block">
                <span class="field-label">Parola actuală</span>
                <input type="password" wire:model="current_password" autocomplete="current-password">
                @error('current_password') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Parola nouă</span>
                <input type="password" wire:model="password" autocomplete="new-password">
                @error('password') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Confirmă parola nouă</span>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password">
            </label>

            <div><button type="submit" class="st-btn st-btn--ink">Schimbă parola</button></div>
        </form>
    </div>
</x-storefront.account>
