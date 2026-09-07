<div class="mx-auto max-w-md">
    <h1 class="mb-1 text-2xl font-black tracking-tight">Ai uitat parola?</h1>
    <p class="mb-6 text-sm text-stone-600">Îți trimitem un link de resetare pe email.</p>

    @if($status)
        <p class="mb-4 rounded-lg border border-lime-300 bg-lime-50 p-3 text-sm text-lime-900">{{ $status }}</p>
    @endif

    <form wire:submit="sendLink" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
            <input type="email" wire:model="email" class="w-full rounded-lg border-stone-300 text-sm">
            @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>
        <button type="submit" class="w-full rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">
            Trimite linkul
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-stone-600">
        <a href="{{ route('customer.login') }}" class="font-semibold underline">Înapoi la autentificare</a>
    </p>
</div>
