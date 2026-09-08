<div>
    <x-admin.page-header :title="$article ? 'Editează articolul' : 'Articol nou'"
                         subtitle="Imagine principală, categorie, conținut și metadate.">
        <x-slot:actions>
            <a href="{{ route('admin.articles.index') }}" class="btn-secondary">← Toate articolele</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    @if($errors->any())
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</p>
    @endif

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <x-admin.panel title="Conținut" subtitle="HTML-ul este filtrat la afișare printr-o listă de etichete permise.">
            <label class="block">
                <span class="field-label">Titlu</span>
                <input wire:model.live.debounce.400ms="title">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Slug</span>
                <input wire:model="slug">
                @error('slug') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Rezumat</span>
                <textarea wire:model="excerpt" rows="3"></textarea>
                @error('excerpt') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Conținut (HTML)</span>
                <textarea wire:model="content" rows="24" class="font-mono text-sm"></textarea>
                @error('content') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <x-admin.section title="SEO">
                <label class="block">
                    <span class="field-label">Titlu SEO</span>
                    <input wire:model="seoTitle">
                </label>

                <label class="block">
                    <span class="field-label">Descriere SEO</span>
                    <textarea wire:model="seoDescription" maxlength="320" rows="2"></textarea>
                </label>

                <label class="block">
                    <span class="field-label">URL canonical</span>
                    <input type="url" wire:model="canonicalUrl">
                </label>

                <div class="flex gap-5 text-sm text-stone-700">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="robotsIndex"> Index</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="robotsFollow"> Follow</label>
                </div>
            </x-admin.section>
        </x-admin.panel>

        <aside class="space-y-6">
            <x-admin.section title="Publicare">
                <label class="block">
                    <span class="field-label">Status</span>
                    <select wire:model="status">
                        @foreach(['draft' => 'Ciornă', 'review' => 'De verificat', 'published' => 'Publicat', 'archived' => 'Arhivat'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="isFeatured"> Articol recomandat
                </label>
            </x-admin.section>

            <x-admin.section title="Categorie">
                <select wire:model="articleCategoryId" aria-label="Categorie">
                    <option value="">Fără categorie</option>
                    @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
            </x-admin.section>

            <x-admin.section title="Imagine principală">
                @if($article?->featured_image_path)
                    <img src="{{ Storage::disk('public')->url($article->featured_image_path) }}"
                         alt="{{ $featuredImageAlt }}" class="w-full rounded-xl bg-stone-100 object-cover">
                @endif

                <input type="file" wire:model="featuredImage" accept="image/*" aria-label="Imagine principală">
                @error('featuredImage') <span class="field-error">{{ $message }}</span> @enderror

                <label class="block">
                    <span class="field-label">Text alternativ</span>
                    <input wire:model="featuredImageAlt">
                    {{-- Not decoration: this is what a screen reader announces and what stands in
                         for the image when it fails to load. --}}
                    <span class="field-hint">Descrie ce se vede în imagine.</span>
                </label>
            </x-admin.section>

            <button type="submit" class="btn-primary w-full">Salvează articolul</button>
        </aside>
    </form>
</div>
