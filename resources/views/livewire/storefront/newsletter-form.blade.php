<div class="w-full max-w-md">
    @if($done !== '')
        <p class="rounded-lg bg-white/10 px-4 py-3 text-sm font-medium text-white">{{ $done }}</p>
    @else
        <form wire:submit="subscribe" class="flex flex-col gap-2 sm:flex-row">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Adresa de e-mail</span>
                <input type="email" wire:model="email" placeholder="adresa@exemplu.ro" required
                       class="w-full border-white/20 bg-white/10 text-white placeholder:text-stone-400 focus:border-white focus:ring-white">
            </label>

            {{-- Off-screen rather than display:none: some bots skip hidden inputs, and none of
                 them skip one that is simply positioned outside the viewport. --}}
            <label class="absolute left-[-9999px]" aria-hidden="true" tabindex="-1">
                Website
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </label>

            <button type="submit" class="shrink-0 rounded-lg bg-white px-5 py-2 text-sm font-semibold text-stone-900 transition hover:bg-stone-200">
                Abonează-mă
            </button>
        </form>

        @error('email') <p class="mt-2 text-sm text-red-300">{{ $message }}</p> @enderror

        <p class="mt-2 text-xs text-stone-400">
            Îți trimitem doar noutăți despre produse și oferte. Te poți dezabona oricând.
        </p>
    @endif
</div>
