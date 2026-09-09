<div>
    <x-admin.page-header title="Lucrări și servicii"
                         subtitle="Vocabularul de lucrări pe care service-urile îl folosesc, legat de categoriile de piese.">
        <x-slot:actions>
            <a href="{{ route('admin.service-shops.index') }}" class="btn-secondary">← Service auto</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <div class="grid gap-8 lg:grid-cols-[22rem_1fr]">
        <div class="space-y-6">
            <form wire:submit="saveCategory">
                <x-admin.panel :title="$categoryId ? 'Editează categoria' : 'Categorie nouă'"
                               subtitle="Grupează lucrările pe fișa service-ului și în pagina de lucrări.">
                    <label class="block">
                        <span class="field-label">Denumire</span>
                        <input wire:model="categoryName" placeholder="Frâne">
                        @error('categoryName') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Iconiță</span>
                        <select wire:model="categoryIcon">
                            <option value="">Fără iconiță</option>
                            @foreach($iconOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('categoryIcon') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex gap-2 border-t border-stone-100 pt-5">
                        <button type="submit" class="btn-primary">Salvează</button>
                        @if($categoryId)
                            <button type="button" wire:click="resetCategoryForm" class="btn-ghost">Renunță</button>
                        @endif
                    </div>
                </x-admin.panel>
            </form>

            <form wire:submit="saveService">
                <x-admin.panel :title="$serviceId ? 'Editează lucrarea' : 'Lucrare nouă'"
                               subtitle="Legătura cu o categorie de piese e ce face taxonomia utilă: pagina lucrării poate oferi piesele.">
                    <label class="block">
                        <span class="field-label">Categorie</span>
                        <select wire:model="serviceCategoryId">
                            <option value="">Alege categoria</option>
                            @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                        </select>
                        @error('serviceCategoryId') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Denumire</span>
                        <input wire:model="serviceName" placeholder="Schimb plăcuțe frână față">
                        @error('serviceName') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Descriere</span>
                        <textarea wire:model="serviceDescription" rows="4"></textarea>
                        <span class="field-hint">Apare pe pagina publică a lucrării.</span>
                    </label>

                    <label class="block">
                        <span class="field-label">Categorie de piese</span>
                        <select wire:model="partsCategoryId">
                            <option value="">Fără legătură</option>
                            @foreach($partsCategories as $category)
                                <option value="{{ $category->id }}">{{ str_repeat('— ', $category->depth) }}{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('partsCategoryId') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Durată tipică (min)</span>
                        <input type="number" min="5" wire:model="duration">
                        @error('duration') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="serviceActive"> Activă
                    </label>

                    <div class="flex gap-2 border-t border-stone-100 pt-5">
                        <button type="submit" class="btn-primary">Salvează</button>
                        @if($serviceId)
                            <button type="button" wire:click="resetServiceForm" class="btn-ghost">Renunță</button>
                            <button type="button" wire:click="deleteService({{ $serviceId }})"
                                    wire:confirm="Ștergi lucrarea? Dispare și din prețurile service-urilor." class="btn-danger">Șterge</button>
                        @endif
                    </div>
                </x-admin.panel>
            </form>
        </div>

        <div class="space-y-8">
            @forelse($categories as $category)
                <x-admin.section :title="$category->name" wire:key="service-category-{{ $category->id }}">
                    <x-slot:aside>
                        <button type="button" wire:click="editCategory({{ $category->id }})" class="hover:text-stone-900 hover:underline">editează</button>
                        @if($category->services_count === 0)
                            <button type="button" wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="Ștergi categoria?" class="ml-2 text-red-700 hover:underline">șterge</button>
                        @endif
                    </x-slot:aside>

                    @if($category->services->isEmpty())
                        <p class="text-sm text-stone-500">Nicio lucrare în această categorie.</p>
                    @else
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th>Lucrare</th>
                                    <th>Categorie de piese</th>
                                    <th class="text-right">Durată</th>
                                    <th class="text-right">Service-uri</th>
                                    <th>Stare</th>
                                    <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($category->services as $service)
                                    <tr wire:key="service-{{ $service->id }}">
                                        <td>
                                            <button type="button" wire:click="editService({{ $service->id }})" class="font-medium text-stone-900 hover:underline">
                                                {{ $service->name }}
                                            </button>
                                            <div class="font-mono text-xs text-stone-400">{{ $service->slug }}</div>
                                        </td>
                                        <td class="text-stone-600">{{ $service->partsCategory?->full_path ?? '—' }}</td>
                                        <td class="text-right tabular-nums">{{ $service->typical_duration_minutes ? $service->typical_duration_minutes.' min' : '—' }}</td>
                                        <td class="text-right tabular-nums">{{ $service->shops_count }}</td>
                                        <td>
                                            <x-admin.status :label="$service->is_active ? 'Activă' : 'Ascunsă'"
                                                            :tone="$service->is_active ? 'positive' : 'neutral'" />
                                        </td>
                                        <td>
                                            <x-admin.row-actions>
                                                <x-admin.row-action wire:click="editService({{ $service->id }})">Editează</x-admin.row-action>
                                                <x-admin.row-action href="{{ route('storefront.service-type', $service->slug) }}">Vezi public</x-admin.row-action>
                                                <x-admin.row-action tone="danger" wire:click="deleteService({{ $service->id }})"
                                                                    wire:confirm="Ștergi lucrarea?">Șterge</x-admin.row-action>
                                            </x-admin.row-actions>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-admin.section>
            @empty
                <x-admin.empty title="Niciun serviciu definit"
                               hint="Adaugă o categorie și apoi lucrările din ea, ca service-urile să le poată prețui." />
            @endforelse
        </div>
    </div>
</div>
