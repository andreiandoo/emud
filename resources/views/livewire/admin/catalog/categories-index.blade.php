<div>
    <x-admin.page-header title="Categorii" subtitle="Arbore complet, imagini, conținut și SEO administrabile.">
        <x-slot:actions>
            <button type="button" wire:click="resetForm" class="btn-primary">Categorie nouă</button>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <form wire:submit="save" class="mb-8">
        <x-admin.panel :title="$editingId ? 'Editează categoria' : 'Categorie nouă'"
                       subtitle="Slugul intră în URL-ul magazinului; schimbarea lui rupe linkurile existente.">
            <div class="grid gap-4 lg:grid-cols-2">
                <label class="block">
                    <span class="field-label">Nume</span>
                    <input wire:model.live.debounce.400ms="name">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Slug</span>
                    <input wire:model="slug">
                    @error('slug') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Părinte</span>
                    <select wire:model="parentId">
                        <option value="">Rădăcină</option>
                        @foreach($parentOptions as $option)
                            <option value="{{ $option->id }}">{{ str_repeat('— ', $option->depth) }}{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Imagine</span>
                    <input type="file" wire:model="image" accept="image/*">
                    @if($currentImage)<span class="field-hint">Curentă: {{ $currentImage }}</span>@endif
                    @error('image') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block lg:col-span-2">
                    <span class="field-label">Descriere</span>
                    <textarea wire:model="description" rows="4"></textarea>
                </label>
            </div>

            <x-admin.section title="SEO">
                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Titlu SEO</span>
                        <input wire:model="seoTitle" maxlength="255">
                    </label>

                    <label class="block">
                        <span class="field-label">Descriere SEO</span>
                        <textarea wire:model="seoDescription" maxlength="320" rows="2"></textarea>
                    </label>

                    <label class="block lg:col-span-2">
                        <span class="field-label">URL canonical</span>
                        <input type="url" wire:model="canonicalUrl">
                    </label>
                </div>
            </x-admin.section>

            <div class="flex flex-wrap gap-5 border-t border-stone-100 pt-5 text-sm text-stone-700">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isActive"> Activă</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isVisibleInMenu"> În meniu</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="robotsIndex"> Indexare SEO</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="robotsFollow"> Follow linkuri</label>
            </div>

            <div class="flex gap-2 border-t border-stone-100 pt-5">
                <button type="submit" class="btn-primary">{{ $editingId ? 'Salvează modificările' : 'Adaugă categoria' }}</button>
                @if($editingId)
                    <button type="button" wire:click="resetForm" class="btn-ghost">Renunță</button>
                    <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Ștergi categoria?" class="btn-danger">Șterge</button>
                @endif
            </div>
        </x-admin.panel>
    </form>

    <label class="relative mb-4 block max-w-md">
        <span class="sr-only">Caută o categorie</span>
        <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.300ms="search" class="pl-9" placeholder="Caută o categorie">
    </label>

    @if($categories->isEmpty())
        <x-admin.empty title="Nicio categorie" hint="Adaugă prima categorie din formularul de mai sus." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Categorie</th>
                        <th class="text-right">Produse</th>
                        <th class="text-right">Filtre</th>
                        <th>Activă</th>
                        <th class="w-24">Ordine</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" @class(['bg-stone-50' => $editingId === $category->id])>
                            <td>
                                <span class="text-stone-300">{{ str_repeat('— ', $category->depth) }}</span>
                                <button type="button" wire:click="edit({{ $category->id }})" class="font-medium text-stone-900 hover:underline">{{ $category->name }}</button>
                                <div class="text-xs text-stone-400">{{ $category->full_path }}</div>
                            </td>
                            <td class="text-right tabular-nums">{{ $category->products_count }}</td>
                            <td class="text-right tabular-nums">{{ $category->attributes_count }}</td>
                            <td>
                                <button type="button" wire:click="toggle({{ $category->id }})">
                                    <x-admin.status :label="$category->is_active ? 'Activă' : 'Ascunsă'"
                                                    :tone="$category->is_active ? 'positive' : 'neutral'" />
                                </button>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <button type="button" wire:click="move({{ $category->id }}, 'up')"
                                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
                                            aria-label="Mută mai sus">↑</button>
                                    <button type="button" wire:click="move({{ $category->id }}, 'down')"
                                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900"
                                            aria-label="Mută mai jos">↓</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
