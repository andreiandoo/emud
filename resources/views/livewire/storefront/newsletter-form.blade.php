<div class="w-full max-w-lg">
    @if($done !== '')
        <p class="flex items-center gap-3 rounded-[3px] border border-gl2 bg-white/[.04] px-4 py-3.5 text-sm font-medium text-bone">
            <x-storefront.icon name="check" class="h-5 w-5 shrink-0 text-fit-bright" /> {{ $done }}
        </p>
    @else
        <form wire:submit="subscribe" class="flex items-center gap-3 border-b border-gl2 pb-2.5 focus-within:border-bone">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Adresa de e-mail</span>
                <input type="email" wire:model="email" placeholder="adresa@exemplu.ro" required
                       class="w-full rounded-none border-0 bg-transparent px-0 text-lg text-bone placeholder:text-mute2 focus:border-0 focus:ring-0">
            </label>

            {{-- Off-screen rather than display:none: some bots skip hidden inputs, and none of
                 them skip one that is simply positioned outside the viewport. --}}
            <label class="absolute left-[-9999px]" aria-hidden="true" tabindex="-1">
                Website
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </label>

            <button type="submit" class="st-btn st-btn--sm shrink-0">
                <span wire:loading.remove wire:target="subscribe">Abonează-mă</span>
                <span wire:loading wire:target="subscribe">Se trimite…</span>
            </button>
        </form>

        @error('email') <p class="mt-2 text-sm text-signal2">{{ $message }}</p> @enderror

        <p class="mt-3 text-xs text-mute2">
            Îți trimitem doar noutăți despre produse și oferte. Te poți dezabona oricând.
        </p>
    @endif
</div>
