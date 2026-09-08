<div>
    <x-admin.page-header title="Branduri" subtitle="Producători disponibili în editorul de produse." />

    <div class="grid gap-8 lg:grid-cols-[24rem_1fr]">
        <form wire:submit="save" class="order-2 lg:order-1">
            <x-admin.panel :title="$editingId ? 'Editează brandul' : 'Brand nou'"
                           subtitle="Numele apare pe fișa produsului și în filtrele magazinului.">
                <label class="block">
                    <span class="field-label">Nume brand</span>
                    <input wire:model="name">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Website</span>
                    <input type="url" wire:model="website" placeholder="https://...">
                    @error('website') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Descriere</span>
                    <textarea wire:model="description" rows="3"></textarea>
                    @error('description') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Logo</span>
                    <input type="file" wire:model="logo" accept="image/*">
                    @error('logo') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="isActive">
                    Activ
                </label>

                <div class="flex gap-2 border-t border-stone-100 pt-5">
                    <button type="submit" class="btn-primary">{{ $editingId ? 'Salvează modificările' : 'Adaugă brandul' }}</button>
                    @if($editingId)
                        <button type="button" wire:click="resetForm" class="btn-ghost">Renunță</button>
                    @endif
                </div>
            </x-admin.panel>
        </form>

        <div class="order-1 lg:order-2">
            @if($brands->isEmpty())
                <x-admin.empty title="Niciun brand" hint="Adaugă primul producător din formularul alăturat." />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th class="text-right">Produse</th>
                                <th>Stare</th>
                                <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($brands as $brand)
                                <tr wire:key="brand-{{ $brand->id }}" @class(['bg-stone-50' => $editingId === $brand->id])>
                                    <td>
                                        <button type="button" wire:click="edit({{ $brand->id }})" class="font-medium text-stone-900 hover:underline">{{ $brand->name }}</button>
                                    </td>
                                    <td class="text-right tabular-nums">{{ $brand->products_count }}</td>
                                    <td>
                                        <x-admin.status :label="$brand->is_active ? 'Activ' : 'Inactiv'"
                                                        :tone="$brand->is_active ? 'positive' : 'neutral'" />
                                    </td>
                                    <td>
                                        <x-admin.row-actions>
                                            <x-admin.row-action wire:click="edit({{ $brand->id }})">Editează</x-admin.row-action>
                                            <x-admin.row-action tone="danger" wire:click="delete({{ $brand->id }})"
                                                                wire:confirm="Ștergi brandul?">Șterge</x-admin.row-action>
                                        </x-admin.row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
