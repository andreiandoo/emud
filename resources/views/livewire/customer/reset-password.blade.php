<div class="mx-auto max-w-md">
    <h1 class="mb-6 text-2xl font-black tracking-tight">Setează o parolă nouă</h1>

    <form wire:submit="resetPassword" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
            <input type="email" wire:model="email" class="w-full rounded-lg border-stone-300 text-sm">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Parolă nouă</span>
            <input type="password" wire:model="password" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Confirmă parola</span>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border-stone-300 text-sm">
        </label>
        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">
            Schimbă parola
        </button>
    </form>
</div>
