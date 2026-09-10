<div>
    <x-admin.page-header :title="$reviewId ? 'Recenzie · '.$reviewerName : 'Recenzie nouă'"
                         subtitle="Se completează din ce a trimis clientul. Nimic nu se publică automat.">
        <x-slot:actions>
            <a href="{{ route('admin.reviews.index') }}" class="btn-ghost">Înapoi la listă</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($saved !== '')
        <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-900">{{ $saved }}</p>
    @endif

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <x-admin.panel title="Recenzia">
                <label class="block">
                    <span class="field-label">Titlu</span>
                    <input wire:model="title" placeholder="Kit de înălțare montat impecabil">
                    @error('title') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Text</span>
                    <textarea wire:model="body" rows="8" placeholder="Ce a scris clientul, în cuvintele lui."></textarea>
                    @error('body') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block max-w-32">
                    <span class="field-label">Notă</span>
                    <select wire:model="rating">
                        <option value="">— fără —</option>
                        @foreach(range(5, 1) as $star)
                            <option value="{{ $star }}">{{ $star }} / 5</option>
                        @endforeach
                    </select>
                    @error('rating') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </x-admin.panel>

            <x-admin.panel title="Clientul" subtitle="Conturile sociale devin linkuri sub recenzie.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Nume</span>
                        <input wire:model="reviewerName" placeholder="Andrei M.">
                        @error('reviewerName') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Localitate</span>
                        <input wire:model="reviewerLocation" placeholder="Brașov">
                        @error('reviewerLocation') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Instagram</span>
                        <input wire:model="reviewerInstagram" placeholder="nume_cont sau link complet">
                        <span class="field-hint">Contul sau linkul complet.</span>
                        @error('reviewerInstagram') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="field-label">Facebook</span>
                        <input wire:model="reviewerFacebook" placeholder="nume.cont">
                        @error('reviewerFacebook') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Poză și video">
                @if($imageUrl)
                    <div class="flex items-start gap-4">
                        <img src="{{ $imageUrl }}" alt="" class="h-32 w-44 rounded-lg border border-stone-200 object-cover">
                        <button type="button" wire:click="removeImage" class="btn-danger">Șterge poza</button>
                    </div>
                @endif

                <label class="block">
                    <span class="field-label">{{ $imageUrl ? 'Înlocuiește poza' : 'Poză' }}</span>
                    <input type="file" wire:model="image" accept="image/*">
                    <span wire:loading wire:target="image" class="field-hint">Se încarcă…</span>
                    @error('image') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Link video</span>
                    <input type="url" wire:model.blur="videoUrl" placeholder="https://www.youtube.com/watch?v=...">
                    <span class="field-hint">YouTube sau Vimeo. Videoul înlocuiește poza pe card.</span>
                    @error('videoUrl') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Publicare">
                <label class="block">
                    <span class="field-label">Stare</span>
                    <select wire:model="reviewStatus">
                        <option value="draft">Ciornă</option>
                        <option value="published">Publicată</option>
                    </select>
                    @error('reviewStatus') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Publicată la</span>
                    <input type="datetime-local" wire:model="publishedAt">
                    <span class="field-hint">Gol înseamnă „acum", la salvare.</span>
                    @error('publishedAt') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="isFeatured"> Evidențiată
                </label>

                <label class="block">
                    <span class="field-label">Ordine</span>
                    <input type="number" wire:model="position">
                    @error('position') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </x-admin.panel>

            <x-admin.panel title="Mașina" subtitle="Ce conduce clientul. Apare sub numele lui.">
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
                    <select wire:model="modelId" @disabled($makeId === '')>
                        <option value="">— fără —</option>
                        @foreach($models as $model)
                            <option value="{{ $model->id }}">{{ $model->name }}</option>
                        @endforeach
                    </select>
                    @error('modelId') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Sau text liber</span>
                    <input wire:model="vehicleLabel" placeholder="Land Rover Defender 110, 1994">
                    <span class="field-hint">Pentru mașinile care nu sunt în baza de date. Are prioritate.</span>
                    @error('vehicleLabel') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </x-admin.panel>

            <x-admin.panel title="Unde apare" subtitle="Recenzia se afișează pe ce alegi aici.">
                <div>
                    <span class="field-label">Produs</span>
                    @if($selectedProduct)
                        <div class="flex items-center gap-2 rounded-lg bg-stone-100 px-3 py-2 text-sm">
                            <span class="min-w-0 flex-1 truncate">{{ $selectedProduct->name }}</span>
                            <button type="button" wire:click="clearProduct" class="text-stone-500 hover:text-stone-900">✕</button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="productSearch" placeholder="minim 3 litere">
                        @if($productResults->isNotEmpty())
                            <ul class="mt-2 divide-y divide-stone-100 rounded-lg border border-stone-200">
                                @foreach($productResults as $result)
                                    <li wire:key="product-{{ $result->id }}">
                                        <button type="button" wire:click="selectProduct({{ $result->id }})"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-stone-50">
                                            {{ $result->name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                    @error('productId') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <span class="field-label">Colecție</span>
                    @if($selectedCollection)
                        <div class="flex items-center gap-2 rounded-lg bg-stone-100 px-3 py-2 text-sm">
                            <span class="min-w-0 flex-1 truncate">{{ $selectedCollection->name }}</span>
                            <button type="button" wire:click="clearCollection" class="text-stone-500 hover:text-stone-900">✕</button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="collectionSearch" placeholder="minim 3 litere">
                        @if($collectionResults->isNotEmpty())
                            <ul class="mt-2 divide-y divide-stone-100 rounded-lg border border-stone-200">
                                @foreach($collectionResults as $result)
                                    <li wire:key="collection-{{ $result->id }}">
                                        <button type="button" wire:click="selectCollection({{ $result->id }})"
                                                class="block w-full px-3 py-2 text-left text-sm hover:bg-stone-50">
                                            {{ $result->name }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                    @error('collectionId') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </x-admin.panel>

            <button type="submit" class="btn-primary w-full">
                {{ $reviewId ? 'Salvează modificările' : 'Adaugă recenzia' }}
            </button>
        </div>
    </form>
</div>
