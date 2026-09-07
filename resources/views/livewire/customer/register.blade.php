<div class="mx-auto max-w-md">
    <h1 class="mb-1 text-2xl font-black tracking-tight">Creează cont</h1>
    <p class="mb-6 text-sm text-stone-600">Salvează-ți mașinile în garaj și vezi doar piesele care li se potrivesc.</p>

    <form wire:submit="register" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Nume</span>
            <input type="text" wire:model="name" autocomplete="name" class="w-full rounded-lg border-stone-300 text-sm">
            @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
            <input type="email" wire:model="email" autocomplete="email" class="w-full rounded-lg border-stone-300 text-sm">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Telefon <span class="font-normal text-stone-400">(opțional)</span></span>
            <input type="tel" wire:model="phone" autocomplete="tel" class="w-full rounded-lg border-stone-300 text-sm">
            @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Parolă</span>
            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Confirmă parola</span>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
        </label>

        <label class="flex items-start gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model="marketing_consent" class="mt-0.5 rounded border-stone-300">
            <span>Vreau să primesc noutăți și oferte pe email. Mă pot dezabona oricând.</span>
        </label>

        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
            Creează contul
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-stone-600">
        Ai deja cont? <a href="{{ route('customer.login') }}" class="font-semibold underline">Autentifică-te</a>
    </p>
</div>
