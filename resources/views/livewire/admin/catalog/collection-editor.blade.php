@php($imageFields = [
    'square_image_path' => ['Imagine pătrată', 'squareImage', 'Apare în caruselul „Alege-ți mașina". Recomandat 800×800.'],
    'wide_image_path' => ['Imagine wide', 'wideImage', 'Bannerul din capul paginii de colecție. Recomandat 1920×720.'],
    'garage_image_path' => ['Imagine pentru garaj', 'garageImage', 'Apare lângă mașina salvată de client. Recomandat 400×300.'],
    'og_image_path' => ['Imagine pentru share', 'ogImage', 'Folosită la partajarea linkului. Recomandat 1200×630.'],
])

<div>
    <x-admin.page-header :title="$collectionId ? $name : 'Colecție nouă'"
                         subtitle="O colecție este catalogul filtrat pe o mașină, cu pagina și materialele ei.">
        <x-slot:actions>
            @if($collectionId)
                <a href="{{ route('storefront.collection', $slug) }}" target="_blank" class="btn-secondary">Vezi în magazin ↗</a>
            @endif
            <a href="{{ route('admin.collections.index') }}" class="btn-ghost">Înapoi la listă</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($saved !== '')
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-900">{{ $saved }}</p>
    @endif

    <x-admin.tabs :tabs="$tabs" :current="$tab" />

    <form wire:submit="save" class="space-y-6">
        @if($tab === 'general')
            <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <x-admin.panel title="Identitate" subtitle="Numele și adresa sub care apare în magazin.">
                    <label class="block">
                        <span class="field-label">Nume</span>
                        <input wire:model.blur="name" placeholder="Suzuki Jimny">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Adresă (slug)</span>
                        <input wire:model="slug" placeholder="suzuki-jimny">
                        <span class="field-hint">/colectii/{{ $slug ?: 'exemplu' }}</span>
                        @error('slug') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Subtitlu</span>
                        <input wire:model="subtitle" placeholder="Piese și accesorii pentru Suzuki Jimny">
                        @error('subtitle') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Descriere</span>
                        <textarea wire:model="description" rows="6"></textarea>
                        <span class="field-hint">Text simplu; rândurile goale se păstrează pe pagină.</span>
                        @error('description') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </x-admin.panel>

                <div class="space-y-6">
                    <x-admin.panel title="Mașina"
                                   subtitle="De aici știe importul ce produse să lege automat de colecție.">
                        <label class="block">
                            <span class="field-label">Marcă</span>
                            <select wire:model.live="makeId">
                                <option value="">— fără —</option>
                                @foreach($makes as $make)
                                    <option value="{{ $make->id }}">{{ $make->name }}</option>
                                @endforeach
                            </select>
                            @error('makeId') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Model</span>
                            <select wire:model.live="modelId" @disabled($makeId === '')>
                                <option value="">— toată marca —</option>
                                @foreach($models as $model)
                                    <option value="{{ $model->id }}">{{ $model->name }}</option>
                                @endforeach
                            </select>
                            @error('modelId') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Generație</span>
                            <select wire:model="generationId" @disabled($modelId === '')>
                                <option value="">— toate generațiile —</option>
                                @foreach($generations as $generation)
                                    <option value="{{ $generation->id }}">
                                        {{ $generation->name }}{{ $generation->year_from ? ' ('.$generation->year_from.'–'.($generation->year_to ?: '') .')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('generationId') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="field-label">An de la</span>
                                <input type="number" wire:model="yearFrom" placeholder="1998">
                                @error('yearFrom') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            <label class="block">
                                <span class="field-label">An până la</span>
                                <input type="number" wire:model="yearTo" placeholder="2018">
                                @error('yearTo') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    </x-admin.panel>

                    <x-admin.panel title="Publicare">
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="isActive"> Publicată în magazin
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="isFeatured"> Apare în caruselul de pe prima pagină
                        </label>
                        <label class="block">
                            <span class="field-label">Ordine</span>
                            <input type="number" wire:model="position">
                            <span class="field-hint">Numărul mai mic apare primul.</span>
                            @error('position') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </x-admin.panel>
                </div>
            </div>
        @endif

        @if($tab === 'media')
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach($imageFields as $column => $field)
                    <x-admin.panel :title="$field[0]" :subtitle="$field[2]" wire:key="image-{{ $column }}">
                        @if($images[$column])
                            <div class="flex items-start gap-4">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($images[$column]) }}"
                                     alt="" class="h-28 w-40 rounded-lg border border-stone-200 object-cover">
                                <button type="button" wire:click="removeImage('{{ $column }}')" class="btn-danger">Șterge</button>
                            </div>
                        @endif

                        <label class="block">
                            <span class="field-label">{{ $images[$column] ? 'Înlocuiește' : 'Încarcă' }}</span>
                            <input type="file" wire:model="{{ $field[1] }}" accept="image/*">
                            <span wire:loading wire:target="{{ $field[1] }}" class="field-hint">Se încarcă…</span>
                            @error($field[1]) <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </x-admin.panel>
                @endforeach

                <x-admin.panel title="Video" subtitle="Link de YouTube sau Vimeo. Se redă direct pe pagina colecției.">
                    <label class="block">
                        <span class="field-label">Link video</span>
                        <input type="url" wire:model.blur="videoUrl" placeholder="https://www.youtube.com/watch?v=...">
                        @error('videoUrl') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    @php($embed = \App\Support\VideoEmbed::url($videoUrl))
                    @if($videoUrl !== '' && ! $embed)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Linkul nu este recunoscut ca YouTube sau Vimeo, așa că pe pagină va apărea doar ca link.
                        </p>
                    @elseif($embed)
                        <div class="aspect-video overflow-hidden rounded-lg bg-stone-950">
                            <iframe src="{{ $embed }}" class="h-full w-full" frameborder="0" allowfullscreen title="Previzualizare"></iframe>
                        </div>
                    @endif
                </x-admin.panel>
            </div>
        @endif

        @if($tab === 'seo')
            <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <x-admin.panel title="Metadate" subtitle="Ce apare în Google și la partajarea linkului.">
                    <label class="block">
                        <span class="field-label">Titlu SEO</span>
                        <input wire:model="seoTitle" placeholder="{{ $name ?: 'Numele colecției' }}">
                        <span class="field-hint">Gol înseamnă că se folosește numele colecției.</span>
                        @error('seoTitle') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Descriere SEO</span>
                        <textarea wire:model="seoDescription" rows="3" maxlength="320"></textarea>
                        <span class="field-hint">Google afișează în jur de 160 de caractere.</span>
                        @error('seoDescription') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </x-admin.panel>

                <x-admin.panel title="Indexare">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="robotsIndex"> Permite indexarea (index)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="robotsFollow"> Permite urmărirea linkurilor (follow)
                    </label>
                    <p class="field-hint">
                        Scoate din index colecțiile fără produse: o pagină goală indexată trage în jos tot domeniul.
                    </p>
                </x-admin.panel>
            </div>
        @endif

        @if($tab === 'products')
            @if(! $collectionId)
                <x-admin.empty title="Salvează întâi colecția"
                               hint="Produsele se leagă după ce colecția există și știe ce mașină descrie." />
            @else
                <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <x-admin.panel :title="'Produse în colecție ('.$products->count().')'"
                                   subtitle="Legăturile automate vin din compatibilitatea declarată a produsului.">
                        <x-slot:actions>
                            <button type="button" wire:click="rebuildProducts" class="btn-secondary">
                                Recalculează automat
                            </button>
                        </x-slot:actions>

                        @if($products->isEmpty())
                            <p class="text-sm text-stone-500">
                                Niciun produs încă. Rulează recalcularea sau adaugă manual din dreapta.
                            </p>
                        @else
                            <div class="table-flat">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Produs</th>
                                            <th>Brand</th>
                                            <th>Legătură</th>
                                            <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($products as $product)
                                            <tr wire:key="attached-{{ $product->id }}">
                                                <td>
                                                    <a href="{{ route('admin.products.edit', $product) }}" class="font-medium text-stone-900 hover:underline">
                                                        {{ $product->name }}
                                                    </a>
                                                </td>
                                                <td class="text-stone-600">{{ $product->brand?->name ?? '—' }}</td>
                                                <td>
                                                    <span class="{{ in_array($product->id, $manualIds, true) ? 'pill-info' : 'pill-neutral' }}">
                                                        {{ in_array($product->id, $manualIds, true) ? 'manual' : 'automat' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <x-admin.row-actions>
                                                        <x-admin.row-action tone="danger" wire:click="detachProduct({{ $product->id }})">
                                                            Scoate din colecție
                                                        </x-admin.row-action>
                                                    </x-admin.row-actions>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($products->count() === 200)
                                <p class="field-hint">Se afișează primele 200 de produse.</p>
                            @endif
                        @endif
                    </x-admin.panel>

                    <x-admin.panel title="Adaugă manual"
                                   subtitle="Un produs adăugat aici rămâne în colecție și după următorul import.">
                        <label class="block">
                            <span class="field-label">Caută produs</span>
                            <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="minim 3 litere">
                        </label>

                        @if($searchResults->isNotEmpty())
                            <ul class="divide-y divide-stone-100">
                                @foreach($searchResults as $result)
                                    <li class="flex items-center gap-3 py-2" wire:key="search-{{ $result->id }}">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm text-stone-800">{{ $result->name }}</span>
                                            @if($result->sku)
                                                <span class="block text-xs text-stone-400">{{ $result->sku }}</span>
                                            @endif
                                        </span>
                                        <button type="button" wire:click="attachProduct({{ $result->id }})" class="btn-secondary">Adaugă</button>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif(mb_strlen($productSearch) >= 3)
                            <p class="text-sm text-stone-500">Niciun produs nelegat care să se potrivească.</p>
                        @endif
                    </x-admin.panel>
                </div>
            @endif
        @endif

        <div class="flex items-center gap-3 border-t border-stone-200 pt-5">
            <button type="submit" class="btn-primary">
                {{ $collectionId ? 'Salvează modificările' : 'Creează colecția' }}
            </button>
            <span wire:loading wire:target="save" class="text-sm text-stone-500">Se salvează…</span>
        </div>
    </form>
</div>
