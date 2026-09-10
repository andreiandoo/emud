<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Footer" subtitle="Coloanele de linkuri se construiesc singure din categorii, colecții și pagini.">
        @if($saved)
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
        @endif

        <x-admin.section title="Prezentare">
            <label class="block">
                <span class="field-label">Text sub siglă</span>
                <textarea wire:model="footer_about" rows="3" placeholder="Piese și accesorii 4x4, off-road și overlanding. Livrăm în toată țara."></textarea>
                @error('footer_about') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </x-admin.section>

        <x-admin.section title="Abonare la noutăți">
            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" wire:model.live="footer_newsletter_enabled">
                Afișează formularul de abonare
            </label>

            <label class="block">
                <span class="field-label">Titlu</span>
                <input wire:model="footer_newsletter_title" @disabled(! $footer_newsletter_enabled) placeholder="Intră în echipă">
                @error('footer_newsletter_title') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Text</span>
                <input wire:model="footer_newsletter_text" @disabled(! $footer_newsletter_enabled) placeholder="Noutăți, teste și oferte. Fără spam.">
                @error('footer_newsletter_text') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </x-admin.section>

        <x-admin.section title="Metode de plată afișate">
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach($methods as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" value="{{ $key }}" wire:model="footer_payment_methods">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="field-hint">Se afișează ca text, nu ca sigle — nu avem drepturi de folosire pentru logourile de card.</p>
        </x-admin.section>

        <x-admin.section title="Rândul de jos">
            <label class="block">
                <span class="field-label">Notă</span>
                <input wire:model="footer_note" placeholder="Prețurile includ TVA.">
                @error('footer_note') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </x-admin.section>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
