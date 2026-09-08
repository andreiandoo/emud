<form wire:submit="save" class="max-w-3xl">
    <x-admin.panel title="Identitatea magazinului" subtitle="Numele, sloganul și imaginile folosite în tot magazinul.">
        @if($saved)
            <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $saved }}</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Titlul site-ului</span>
                <input wire:model="site_title">
                @error('site_title') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Slogan</span>
                <input wire:model="site_tagline" placeholder="Piese 4x4 pentru drumuri care nu există">
                @error('site_tagline') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="field-label">Descriere (meta description)</span>
            <textarea wire:model="site_description" rows="2" maxlength="160"></textarea>
            <span class="field-hint">Maximum 160 de caractere — atât afișează motoarele de căutare.</span>
            @error('site_description') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Monedă</span>
                <input wire:model="default_currency" maxlength="3" placeholder="RON">
                @error('default_currency') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Fus orar</span>
                <input wire:model="timezone" placeholder="Europe/Bucharest">
                @error('timezone') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <x-admin.section title="Imagini">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span class="field-label">Logo</span>
                    @if($logoPath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                             alt="Logo curent" class="mb-2 h-12 w-auto rounded-lg bg-stone-100 p-1">
                    @endif
                    <input type="file" wire:model="logoUpload" accept="image/*">
                    <span class="field-hint">PNG sau SVG, maximum 2 MB.</span>
                    @error('logoUpload') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <span class="field-label">Favicon</span>
                    @if($faviconPath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath) }}"
                             alt="Favicon curent" class="mb-2 h-8 w-8 rounded-lg bg-stone-100 p-1">
                    @endif
                    <input type="file" wire:model="faviconUpload" accept="image/png,image/x-icon">
                    {{-- SVG is excluded on purpose: the favicon is served on every page and an SVG can
                         carry script. --}}
                    <span class="field-hint">PNG sau ICO, maximum 512 KB. SVG nu este acceptat.</span>
                    @error('faviconUpload') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </x-admin.section>

        <div class="border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează</button>
        </div>
    </x-admin.panel>
</form>
