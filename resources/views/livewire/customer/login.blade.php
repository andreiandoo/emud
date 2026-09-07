<div class="mx-auto max-w-md">
    <h1 class="mb-1 text-2xl font-black tracking-tight">Autentificare</h1>
    <p class="mb-6 text-sm text-stone-600">Intră în cont ca să îți accesezi garajul și comenzile.</p>

    @if(session('status'))
        <p class="mb-4 rounded-lg border border-lime-300 bg-lime-50 p-3 text-sm text-lime-900">{{ session('status') }}</p>
    @endif

    <form wire:submit="authenticate" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
            <input type="email" wire:model="email" autocomplete="email" class="w-full rounded-lg border-stone-300 text-sm">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Parolă</span>
            <input type="password" wire:model="password" autocomplete="current-password" class="w-full rounded-lg border-stone-300 text-sm">
            @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="remember" class="rounded border-stone-300">
                Ține-mă minte
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-stone-600 underline">Am uitat parola</a>
        </div>

        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">
            Intră în cont
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-stone-600">
        Nu ai cont? <a href="{{ route('customer.register') }}" class="font-semibold underline">Creează unul</a>
    </p>
</div>
