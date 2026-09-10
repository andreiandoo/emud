<x-storefront.account active="profile" title="Datele mele" intro="Profilul, adresa de livrare, datele de facturare și parola contului.">
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

        <form wire:submit="saveShipping" class="grid content-start gap-5 rounded-[3px] bg-white p-6 sm:p-8">
            <div class="grid gap-2">
                <h2 class="font-display text-2xl font-semibold">Adresa de livrare</h2>
                <p class="text-sm text-ink2">O completăm automat când finalizezi o comandă. O poți schimba și acolo, pentru o singură comandă.</p>
            </div>

            <x-storefront.address-fields prefix="shipping" />

            <div><button type="submit" class="st-btn st-btn--ink">Salvează adresa</button></div>
        </form>

        {{-- Company or person is a switch, not a second form: the same customer orders for
             the house one week and for the business the next. --}}
        <form wire:submit="saveBilling" class="grid content-start gap-5 rounded-[3px] bg-white p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-2">
                    <h2 class="font-display text-2xl font-semibold">Date de facturare</h2>
                    <p class="text-sm text-ink2">Pe cine emitem factura: pe tine sau pe firma ta.</p>
                </div>
                <x-storefront.billing-switch />
            </div>

            @if($billingType === 'company')
                <x-storefront.address-fields prefix="billing" :company="true" />
                <p class="flex items-start gap-2 text-xs text-ink2">
                    <x-storefront.icon name="shield" class="h-4 w-4 shrink-0" />
                    Factura se emite pe firmă. Persoana de contact rămâne cea de la livrare.
                </p>
            @else
                <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                    <input type="checkbox" wire:model.live="billingSame">
                    <span>Aceleași date ca la livrare</span>
                </label>

                @unless($billingSame)
                    <x-storefront.address-fields prefix="billing" />
                @endunless
            @endif

            <div><button type="submit" class="st-btn st-btn--ink">Salvează datele de facturare</button></div>
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
