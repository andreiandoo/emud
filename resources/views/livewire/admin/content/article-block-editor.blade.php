<div class="space-y-6">
    <x-admin.page-header :title="$article->title" subtitle="Conținut pe blocuri și mașinile de care ține articolul.">
        <x-slot:actions>
            <a href="{{ route('admin.articles.edit', $article) }}" class="btn-secondary">Date generale</a>
            @if($article->status === 'published')
                <a href="{{ route('storefront.guide', $article->slug) }}" target="_blank" rel="noopener" class="btn-secondary">Vezi public</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($status)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $status }}</p>
    @endif

    <x-admin.panel title="Mașini asociate"
                   subtitle="Un carusel de piese setat pe „mașinile articolului” folosește aceste legături ca să afișeze piese compatibile.">

        @if($vehicles->isNotEmpty())
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach($vehicles as $link)
                    <span class="flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1 text-sm">
                        {{ $link->label() }}
                        <button wire:click="removeVehicle({{ $link->id }})" class="text-red-600" title="Șterge">×</button>
                    </span>
                @endforeach
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block">
                <span class="field-label">Marcă</span>
                <select wire:model.live="vehicleMakeId" >
                    <option value="">Selectează</option>
                    @foreach($makes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach
                </select>
                @error('vehicleMakeId') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Model</span>
                <select wire:model.live="vehicleModelId" @disabled($models->isEmpty()) >
                    <option value="">Toate</option>
                    @foreach($models as $model)<option value="{{ $model->id }}">{{ $model->name }}</option>@endforeach
                </select>
                @error('vehicleModelId') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Generație</span>
                <select wire:model.live="vehicleGenerationId" @disabled($generations->isEmpty()) >
                    <option value="">Toate</option>
                    @foreach($generations as $generation)<option value="{{ $generation->id }}">{{ $generation->name }}</option>@endforeach
                </select>
                @error('vehicleGenerationId') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <div class="flex items-end">
                <button type="button" wire:click="addVehicle" class="btn-primary w-full">Leagă</button>
            </div>
        </div>
    </x-admin.panel>

    <section class="space-y-4">
        @forelse($blocks as $index => $block)
            <div class="card-padded" wire:key="block-{{ $block['id'] }}">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold">{{ $block['label'] }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="moveBlock({{ $block['id'] }}, -1)"
                                class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900" aria-label="Mută mai sus">↑</button>
                        <button type="button" wire:click="moveBlock({{ $block['id'] }}, 1)"
                                class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-900" aria-label="Mută mai jos">↓</button>
                        <button type="button" wire:click="removeBlock({{ $block['id'] }})" wire:confirm="Ștergi blocul?"
                                class="rounded-lg px-2 py-1 text-sm font-semibold text-red-700 transition hover:bg-red-50">Șterge</button>
                    </div>
                </div>

                @switch($block['type'])
                    @case('rich_text')
                        <textarea wire:model="blocks.{{ $index }}.data.html" rows="8" class="font-mono text-xs" placeholder="<p>Text…</p>"></textarea>
                        <p class="mt-1 text-xs text-stone-500">HTML filtrat la afișare printr-o listă de etichete permise.</p>
                        @break

                    @case('image')
                        <div class="grid gap-3 sm:grid-cols-3">
                            <input wire:model="blocks.{{ $index }}.data.url" placeholder="URL imagine" >
                            <input wire:model="blocks.{{ $index }}.data.alt" placeholder="Text alternativ" >
                            <input wire:model="blocks.{{ $index }}.data.caption" placeholder="Legendă" >
                        </div>
                        @break

                    @case('gallery')
                        <div class="space-y-2">
                            @for($i = 0; $i < 6; $i++)
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input wire:model="blocks.{{ $index }}.data.images.{{ $i }}.url" placeholder="URL imagine {{ $i + 1 }}" >
                                    <input wire:model="blocks.{{ $index }}.data.images.{{ $i }}.alt" placeholder="Text alternativ" >
                                </div>
                            @endfor
                        </div>
                        @break

                    @case('video')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input wire:model="blocks.{{ $index }}.data.url" placeholder="Link YouTube sau Vimeo" >
                            <input wire:model="blocks.{{ $index }}.data.caption" placeholder="Legendă" >
                        </div>
                        @if(($block['data']['url'] ?? '') !== '' && ! ($block['data']['recognised'] ?? true))
                            <p class="mt-2 text-xs font-semibold text-red-600">
                                Linkul nu a fost recunoscut. Sunt acceptate doar YouTube și Vimeo.
                            </p>
                        @endif
                        @break

                    @case('callout')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input wire:model="blocks.{{ $index }}.data.title" placeholder="Titlu" >
                            <select wire:model="blocks.{{ $index }}.data.tone" >
                                <option value="info">Informativ</option>
                                <option value="warning">Atenționare</option>
                                <option value="danger">Pericol</option>
                            </select>
                        </div>
                        <textarea wire:model="blocks.{{ $index }}.data.html" rows="3" class="mt-2 font-mono text-xs"></textarea>
                        @break

                    @case('steps')
                        <div class="space-y-2">
                            @for($i = 0; $i < 8; $i++)
                                <div class="grid gap-2 sm:grid-cols-[12rem_1fr]">
                                    <input wire:model="blocks.{{ $index }}.data.steps.{{ $i }}.title" placeholder="Pasul {{ $i + 1 }}" >
                                    <input wire:model="blocks.{{ $index }}.data.steps.{{ $i }}.text" placeholder="Ce se face" >
                                </div>
                            @endfor
                        </div>
                        @break

                    @case('parts_carousel')
                        <div class="grid gap-3 sm:grid-cols-4">
                            <input wire:model="blocks.{{ $index }}.data.title" placeholder="Titlu secțiune" >
                            <select wire:model.live="blocks.{{ $index }}.data.source" >
                                <option value="vehicle">Mașinile articolului</option>
                                <option value="category">O categorie</option>
                                <option value="products">Listă de produse</option>
                            </select>
                            <select wire:model="blocks.{{ $index }}.data.category_id" >
                                <option value="">Categorie…</option>
                                @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->full_path }}</option>@endforeach
                            </select>
                            <input type="number" wire:model="blocks.{{ $index }}.data.limit" min="1" max="12" >
                        </div>
                        <input wire:model="blocks.{{ $index }}.data.product_ids" placeholder="ID-uri produse, separate prin virgulă" class="mt-2">
                        <p class="mt-1 text-xs text-stone-500">
                            Caruselul se rezolvă la afișare, deci nu rămâne cu piese ieșite din catalog.
                        </p>
                        @break
                @endswitch
            </div>
        @empty
            <x-admin.empty title="Niciun bloc" hint="Articolul nu are încă blocuri. Adaugă primul din bara de mai jos." />
        @endforelse
    </section>

    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-stone-100 p-5">
        <label class="w-56">
            <span class="sr-only">Tip de bloc</span>
            <select wire:model="newBlockType">
                @foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
            </select>
        </label>

        <button type="button" wire:click="addBlock" class="btn-secondary">Adaugă bloc</button>
        <button type="button" wire:click="saveBlocks" class="btn-primary ml-auto">Salvează blocurile</button>
    </div>
</div>
