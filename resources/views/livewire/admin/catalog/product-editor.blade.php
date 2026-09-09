<div x-data="{ tab: 'general' }">
    <x-admin.page-header :title="$product ? 'Editează produsul' : 'Produs nou'"
                         subtitle="Catalog, variante, filtre, media, compatibilitate și SEO.">
        <x-slot:actions>
            <a href="{{ route('admin.products.index') }}" class="btn-secondary">← Toate produsele</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    @if($errors->any())
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            Verifică toate câmpurile marcate. {{ $errors->first() }}
        </p>
    @endif

    {{-- Alpine rather than Livewire tabs: switching a tab must not lose what has been typed into
         the other ones, and every field on this form belongs to a single unsaved product. --}}
    <div class="mb-6 flex flex-wrap items-center gap-1">
        @foreach([
            'general' => 'General',
            'variants' => 'Variante & preț',
            'attributes' => 'Filtre',
            'media' => 'Imagini',
            'fitments' => 'Compatibilitate',
            'seo' => 'SEO',
        ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-stone-100 font-semibold text-stone-900' : 'text-stone-500 hover:bg-stone-50 hover:text-stone-900'"
                    class="rounded-lg px-3 py-1.5 text-sm transition">{{ $label }}</button>
        @endforeach
    </div>

    <form wire:submit="save" class="card-padded space-y-5">
        <section x-show="tab === 'general'" class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span class="field-label">Nume *</span>
                <input wire:model.live.debounce.400ms="name">
                @error('name') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Slug *</span>
                <input wire:model="slug">
                @error('slug') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">SKU produs</span>
                <input wire:model="sku">
                @error('sku') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Cod producător</span>
                <input wire:model="manufacturerPartNumber">
            </label>

            <label class="block">
                <span class="field-label">Brand</span>
                <select wire:model="brandId">
                    <option value="">Fără brand</option>
                    @foreach($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label">Status</span>
                <select wire:model="status">
                    @foreach(['draft' => 'Ciornă', 'review' => 'De verificat', 'active' => 'Publicat', 'archived' => 'Arhivat'] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block lg:col-span-2">
                <span class="field-label">Categorii *</span>
                <input type="search" wire:model.live.debounce.400ms="categorySearch" placeholder="Caută o categorie…" class="mb-2">
                <select wire:model.live="categoryIds" multiple size="8">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ str_repeat('— ', $category->depth) }}{{ $category->name }}</option>
                    @endforeach
                </select>
                <span class="field-hint">Prima selectată devine categoria principală. Cele deja alese rămân în listă oricât ai filtra.</span>
                @error('categoryIds') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            <label class="block lg:col-span-2">
                <span class="field-label">Descriere scurtă</span>
                <textarea wire:model="shortDescription" rows="3"></textarea>
            </label>

            <div class="block lg:col-span-2">
                <span class="field-label">Descriere completă</span>

                {{-- The editor keeps its own DOM, so the whole block is hidden from Livewire's
                     re-render and the value travels through the entangled property instead. --}}
                <div wire:ignore x-data="{ ...richTextEditor(), content: @entangle('description') }"
                     class="overflow-hidden rounded-xl border border-stone-300 bg-white">
                    <div class="flex flex-wrap items-center gap-1 border-b border-stone-200 bg-stone-50 p-1.5">
                        @foreach([
                            ['toggleBold', 'bold', 'B', 'Îngroșat', 'font-bold'],
                            ['toggleItalic', 'italic', 'I', 'Cursiv', 'italic'],
                            ['toggleStrike', 'strike', 'S', 'Tăiat', 'line-through'],
                        ] as [$command, $mark, $glyph, $title, $style])
                            <button type="button" :title="'{{ $title }}'" @click="run('{{ $command }}')"
                                    :class="isActive('{{ $mark }}') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                    class="h-7 w-7 rounded-md text-xs {{ $style }}">{{ $glyph }}</button>
                        @endforeach

                        <span class="mx-1 h-4 w-px bg-stone-300"></span>

                        @foreach([2 => 'H2', 3 => 'H3', 4 => 'H4'] as $level => $glyph)
                            <button type="button" @click="run('toggleHeading', { level: {{ $level }} })"
                                    :class="isActive('heading', { level: {{ $level }} }) ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                    class="h-7 rounded-md px-2 text-xs font-semibold">{{ $glyph }}</button>
                        @endforeach

                        <span class="mx-1 h-4 w-px bg-stone-300"></span>

                        <button type="button" title="Listă cu buline" @click="run('toggleBulletList')"
                                :class="isActive('bulletList') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                class="h-7 rounded-md px-2 text-xs">• Listă</button>
                        <button type="button" title="Listă numerotată" @click="run('toggleOrderedList')"
                                :class="isActive('orderedList') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                class="h-7 rounded-md px-2 text-xs">1. Listă</button>
                        <button type="button" title="Link" @click="setLink()"
                                :class="isActive('link') ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                class="h-7 rounded-md px-2 text-xs">Link</button>

                        <button type="button" @click="toggleSource()"
                                :class="source ? 'bg-stone-900 text-white' : 'text-stone-600 hover:bg-stone-200'"
                                class="ml-auto h-7 rounded-md px-2 text-xs font-mono">&lt;/&gt; HTML</button>
                    </div>

                    <div x-ref="editor" x-show="!source"></div>
                    <textarea x-show="source" x-cloak x-model="content" rows="14"
                              class="w-full border-0 font-mono text-xs focus:ring-0"></textarea>
                </div>

                <span class="field-hint">Butonul HTML comută pe sursă, pentru descrierile care vin dezordonate din feed-uri.</span>
            </div>

            <label class="block">
                <span class="field-label">Garanție (luni)</span>
                <input type="number" min="0" wire:model="warrantyMonths">
            </label>

            <label class="block">
                <span class="field-label">Greutate (kg)</span>
                <input type="number" step="0.001" min="0" wire:model="weightKg">
            </label>

            <div class="flex flex-wrap gap-5 text-sm text-stone-700 lg:col-span-2">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isUniversal"> Produs universal</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isFeatured"> Recomandat</label>
            </div>
        </section>

        <section x-show="tab === 'variants'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Variante comerciale</h2>
                <button type="button" wire:click="addVariant" class="btn-secondary">+ Variantă</button>
            </div>

            @foreach($variants as $index => $variant)
                <div wire:key="variant-{{ $index }}" class="grid gap-4 rounded-xl bg-stone-50 p-4 md:grid-cols-4">
                    <label class="block">
                        <span class="field-label">Nume</span>
                        <input wire:model="variants.{{ $index }}.name">
                    </label>
                    <label class="block">
                        <span class="field-label">SKU *</span>
                        <input wire:model="variants.{{ $index }}.sku">
                        @error('variants.'.$index.'.sku') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Cod producător</span>
                        <input wire:model="variants.{{ $index }}.mpn">
                    </label>
                    <label class="block">
                        <span class="field-label">EAN / UPC</span>
                        <input wire:model="variants.{{ $index }}.barcode">
                    </label>
                    <label class="block">
                        <span class="field-label">Preț</span>
                        <input type="number" step="0.01" min="0" wire:model="variants.{{ $index }}.price">
                        @error('variants.'.$index.'.price') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="block">
                        <span class="field-label">Preț vechi</span>
                        <input type="number" step="0.01" min="0" wire:model="variants.{{ $index }}.compare_at_price">
                    </label>
                    <label class="block">
                        <span class="field-label">Greutate (kg)</span>
                        <input type="number" step="0.001" min="0" wire:model="variants.{{ $index }}.weight_kg">
                    </label>

                    <div class="flex items-end justify-between gap-3 pb-2">
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="variants.{{ $index }}.is_active"> Activă
                        </label>

                        @if(count($variants) > 1)
                            <button type="button" wire:click="removeVariant({{ $index }})"
                                    class="text-sm font-semibold text-red-700 hover:underline">Elimină</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>

        <section x-show="tab === 'attributes'" x-cloak class="space-y-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                Atribute disponibile pentru categoriile selectate
            </h2>

            <div class="grid gap-4 md:grid-cols-2">
                @forelse($attributes as $attribute)
                    <label class="block" wire:key="attribute-{{ $attribute->id }}">
                        <span class="field-label">{{ $attribute->name }}@if($attribute->unit) ({{ $attribute->unit }})@endif</span>

                        @if(in_array($attribute->type, ['select', 'color'], true))
                            <select wire:model="attributeValues.{{ $attribute->id }}">
                                <option value="">—</option>
                                @foreach($attribute->options as $option)<option value="{{ $option->id }}">{{ $option->label }}</option>@endforeach
                            </select>
                        @elseif($attribute->type === 'multiselect')
                            <select multiple wire:model="attributeValues.{{ $attribute->id }}">
                                @foreach($attribute->options as $option)<option value="{{ $option->id }}">{{ $option->label }}</option>@endforeach
                            </select>
                        @elseif($attribute->type === 'boolean')
                            <span class="mt-1 flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model="attributeValues.{{ $attribute->id }}"> Da
                            </span>
                        @else
                            <input type="{{ $attribute->type === 'number' ? 'number' : 'text' }}" wire:model="attributeValues.{{ $attribute->id }}">
                        @endif

                        @if($attribute->help_text)<span class="field-hint">{{ $attribute->help_text }}</span>@endif
                    </label>
                @empty
                    <p class="text-sm text-stone-500 md:col-span-2">
                        Selectează cel puțin o categorie sau definește atribute globale.
                    </p>
                @endforelse
            </div>
        </section>

        <section x-show="tab === 'media'" x-cloak class="space-y-4">
            <label class="block">
                <span class="field-label">Încarcă imagini</span>
                <input type="file" multiple accept="image/*" wire:model="images">
                @error('images.*') <span class="field-error">{{ $message }}</span> @enderror
            </label>

            @if(count($existingMedia) > 0)
                <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                    @foreach($existingMedia as $media)
                        <figure class="overflow-hidden rounded-xl border border-stone-200" wire:key="media-{{ $media->id }}">
                            <img src="{{ Storage::disk($media->disk)->url($media->path) }}" alt="{{ $media->alt_text }}"
                                 class="aspect-square w-full bg-stone-100 object-cover">
                            <figcaption class="p-2 text-center">
                                <button type="button" wire:click="removeMedia({{ $media->id }})"
                                        class="text-xs font-semibold text-red-700 hover:underline">Elimină</button>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </section>

        <section x-show="tab === 'fitments'" x-cloak class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Compatibilitate auto</h2>
                <button type="button" wire:click="addFitment" class="btn-secondary">+ Compatibilitate</button>
            </div>

            <label class="block">
                <span class="field-label">Caută vehiculul</span>
                <input type="search" wire:model.live.debounce.400ms="fitmentSearch" placeholder="Marcă, model sau generație — ex. Suzuki Jimny">
                <span class="field-hint">Catalogul are zeci de mii de generații, așa că lista se completează după ce cauți. Generațiile deja alese rămân vizibile.</span>
            </label>

            @foreach($fitments as $index => $fitment)
                <div wire:key="fitment-{{ $index }}" class="grid gap-4 rounded-xl bg-stone-50 p-4 md:grid-cols-5">
                    <label class="block md:col-span-2">
                        <span class="field-label">Generație</span>
                        <select wire:model="fitments.{{ $index }}.generation_id">
                            <option value="">{{ $generations->isEmpty() ? 'Caută mai întâi un vehicul' : 'Alege generația' }}</option>
                            @foreach($generations as $generation)
                                <option value="{{ $generation->id }}">
                                    {{ $generation->label }} ({{ $generation->year_from }}–{{ $generation->year_to ?: 'prezent' }})
                                </option>
                            @endforeach
                        </select>
                        @error('fitments.'.$index.'.generation_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">An de la</span>
                        <input type="number" wire:model="fitments.{{ $index }}.year_from">
                    </label>

                    <label class="block">
                        <span class="field-label">An până la</span>
                        <input type="number" wire:model="fitments.{{ $index }}.year_to">
                    </label>

                    <label class="block">
                        <span class="field-label">Poziție</span>
                        <input wire:model="fitments.{{ $index }}.position" placeholder="față, spate, stânga...">
                    </label>

                    <label class="block md:col-span-3">
                        <span class="field-label">Note montaj</span>
                        <textarea wire:model="fitments.{{ $index }}.notes" rows="2"></textarea>
                    </label>

                    <div class="flex items-end justify-between gap-3 pb-2 md:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="fitments.{{ $index }}.requires_modification"> Necesită modificări
                        </label>

                        <button type="button" wire:click="removeFitment({{ $index }})"
                                class="text-sm font-semibold text-red-700 hover:underline">Elimină</button>
                    </div>
                </div>
            @endforeach
        </section>

        <section x-show="tab === 'seo'" x-cloak class="grid gap-4">
            <label class="block">
                <span class="field-label">Titlu SEO</span>
                <input wire:model="seoTitle" maxlength="255">
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
        </section>

        <div class="flex justify-between border-t border-stone-100 pt-5">
            <button type="submit" class="btn-primary">Salvează produsul</button>

            @if($product)
                <button type="button" wire:click="deleteProduct" wire:confirm="Arhivezi produsul?" class="btn-danger">Arhivează produsul</button>
            @endif
        </div>
    </form>
</div>
