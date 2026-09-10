<x-storefront.auth title="Ai uitat parola?" intro="Îți trimitem un link de resetare pe email.">
    @if($status)
        <p class="mb-6 flex items-start gap-2.5 rounded-[3px] bg-sand px-4 py-3 text-sm text-sandink">
            <x-storefront.icon name="mail" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $status }}
        </p>
    @endif

    <form wire:submit="sendLink" class="grid gap-5">
        <label class="block">
            <span class="field-label">Email</span>
            <input type="email" wire:model="email" autocomplete="email">
            @error('email') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <button type="submit" class="st-btn st-btn--block">
            <span wire:loading.remove wire:target="sendLink">Trimite linkul</span>
            <span wire:loading wire:target="sendLink">Se trimite…</span>
        </button>
    </form>

    <p class="mt-8 border-t border-line pt-6 text-sm text-ink2">
        <a href="{{ route('customer.login') }}" class="inline-flex items-center gap-2 font-semibold text-ink underline underline-offset-2">
            <x-storefront.icon name="arrow-left" class="h-4 w-4" /> Înapoi la autentificare
        </a>
    </p>
</x-storefront.auth>
