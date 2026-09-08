<div>
    <x-admin.page-header title="Filtre și atribute" subtitle="Definiții reutilizabile, opțiuni și reguli per categorie.">
        <x-slot:actions>
            <button type="button" wire:click="resetForm" class="btn-primary">Filtru nou</button>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <form wire:submit="save" class="mb-8">
        <x-admin.panel :title="$editingId ? 'Editează atributul' : 'Atribut nou'"
                       subtitle="Codul intern este cheia sub care valoarea ajunge în feeduri și în API; nu îl schimba după ce a fost folosit.">
            <div class="grid gap-4 lg:grid-cols-3">
                <label class="block">
                    <span class="field-label">Nume</span>
                    <input wire:model.live.debounce.400ms="name">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Cod intern</span>
                    <input wire:model="code" class="font-mono text-sm">
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Tip</span>
                    <select wire:model.live="type">
                        @foreach(['text', 'number', 'boolean', 'select', 'multiselect', 'color'] as $item)
                            <option value="{{ $item }}">{{ $item }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Unitate</span>
                    <input wire:model="unit" placeholder="mm, kg, inch...">
                </label>

                <label class="block lg:col-span-2">
                    <span class="field-label">Ajutor pentru administrator/client</span>
                    <input wire:model="helpText">
                </label>

                @if(in_array($type, ['select', 'multiselect', 'color'], true))
                    <label class="block lg:col-span-3">
                        <span class="field-label">Opțiuni</span>
                        <textarea wire:model="optionsText" rows="5" class="font-mono text-sm"></textarea>
                        <span class="field-hint">Câte una pe linie, în forma <code>Etichetă|valoare</code>.</span>
                    </label>
                @endif

                <label class="block lg:col-span-3">
                    <span class="field-label">Categorii</span>
                    <select wire:model="categoryIds" multiple size="8">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ str_repeat('— ', $category->depth) }}{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <span class="field-hint">Un atribut global se aplică peste tot, indiferent de selecția de aici.</span>
                </label>
            </div>

            <div class="flex flex-wrap gap-5 border-t border-stone-100 pt-5 text-sm text-stone-700">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isActive"> Activ</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isGlobal"> Global</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isFilterable"> Filtrabil</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isComparable"> Comparabil</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isRequired"> Obligatoriu</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isVariantDefining"> Definește varianta</label>
            </div>

            <div class="flex gap-2 border-t border-stone-100 pt-5">
                <button type="submit" class="btn-primary">Salvează</button>
                @if($editingId)
                    <button type="button" wire:click="resetForm" class="btn-ghost">Renunță</button>
                    <button type="button" wire:click="delete({{ $editingId }})" wire:confirm="Ștergi atributul și opțiunile lui?" class="btn-danger">Șterge</button>
                @endif
            </div>
        </x-admin.panel>
    </form>

    @if($attributes->isEmpty())
        <x-admin.empty title="Niciun atribut" hint="Atributele definesc filtrele magazinului și specificațiile de pe fișa produsului." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Atribut</th>
                        <th>Cod</th>
                        <th>Tip</th>
                        <th>Unitate</th>
                        <th class="text-right">Categorii</th>
                        <th class="text-right">Opțiuni</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attributes as $attribute)
                        <tr wire:key="attribute-{{ $attribute->id }}" @class(['bg-stone-50' => $editingId === $attribute->id])>
                            <td>
                                <button type="button" wire:click="edit({{ $attribute->id }})" class="font-medium text-stone-900 hover:underline">{{ $attribute->name }}</button>
                            </td>
                            <td class="font-mono text-xs text-stone-500">{{ $attribute->code }}</td>
                            <td class="text-stone-600">{{ $attribute->type }}</td>
                            <td class="text-stone-600">{{ $attribute->unit ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ $attribute->categories_count }}</td>
                            <td class="text-right tabular-nums">{{ $attribute->options_count }}</td>
                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action wire:click="edit({{ $attribute->id }})">Editează</x-admin.row-action>
                                    <x-admin.row-action tone="danger" wire:click="delete({{ $attribute->id }})"
                                                        wire:confirm="Ștergi atributul și opțiunile lui?">Șterge</x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
