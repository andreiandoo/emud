<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-black tracking-tight">Datele mele</h1>

    @if($status)
        <p class="rounded-lg border border-lime-300 bg-lime-50 p-3 text-sm text-lime-900">{{ $status }}</p>
    @endif

    <form wire:submit="saveProfile" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="text-lg font-bold">Profil</h2>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Nume</span>
            <input type="text" wire:model="name" class="w-full rounded-lg border-stone-300 text-sm">
            @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
            <input type="email" wire:model="email" class="w-full rounded-lg border-stone-300 text-sm">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Telefon</span>
            <input type="tel" wire:model="phone" class="w-full rounded-lg border-stone-300 text-sm">
        </label>
        <label class="flex items-start gap-2 text-sm text-stone-600">
            <input type="checkbox" wire:model="marketing_consent" class="mt-0.5 rounded border-stone-300">
            <span>Vreau să primesc noutăți și oferte pe email.</span>
        </label>
        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">Salvează</button>
    </form>

    <form wire:submit="changePassword" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="text-lg font-bold">Schimbă parola</h2>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Parola actuală</span>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-lg border-stone-300 text-sm">
            @error('current_password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Parola nouă</span>
            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Confirmă parola nouă</span>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
        </label>
        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">Schimbă parola</button>
    </form>
</div>
