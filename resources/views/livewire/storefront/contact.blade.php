<div class="mx-auto max-w-2xl space-y-6">
    <x-seo title="Contact" description="Scrie-ne despre o comandă, o piesă sau compatibilitatea cu mașina ta." />

    <div>
        <h1 class="text-2xl font-black tracking-tight">Contact</h1>
        <p class="mt-1 text-sm text-stone-600">
            Scrie-ne despre o comandă, o piesă sau compatibilitatea cu mașina ta. Îți răspundem pe email.
        </p>
    </div>

    @if($sent)
        <p class="rounded-lg border border-lime-300 bg-lime-50 p-3 text-sm text-lime-900">{{ $sent }}</p>
    @endif

    <form wire:submit="send" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <div class="grid gap-3 sm:grid-cols-2">
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
                <span class="mb-1 block text-xs font-medium text-stone-600">Telefon <span class="font-normal text-stone-400">(opțional)</span></span>
                <input type="tel" wire:model="phone" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Subiect <span class="font-normal text-stone-400">(opțional)</span></span>
                <input type="text" wire:model="subject" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Mesaj</span>
            <textarea wire:model="message" rows="6" class="w-full rounded-lg border-stone-300 text-sm"></textarea>
            @error('message') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        {{-- Hidden from people, visible to bots. aria-hidden and tabindex keep it out of the
             way of screen readers and keyboard navigation. --}}
        <div class="hidden" aria-hidden="true">
            <label>Website<input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <button type="submit" class="rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-stone-700">
            Trimite mesajul
        </button>
    </form>
</div>
