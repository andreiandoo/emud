<div class="space-y-6">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-base font-semibold">{{ $article->title }}</h1>
            <p class="text-sm text-stone-500">Conținut pe blocuri și mașinile de care ține articolul.</p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('admin.articles.edit', $article) }}" class="underline">Date generale</a>
            @if($article->status === 'published')
                <a href="{{ route('storefront.guide', $article->slug) }}" target="_blank" class="underline">Vezi public</a>
            @endif
        </div>
    </div>

    @if($status)
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $status }}</p>
    @endif

    <section class="card-padded">
        <h2 class="mb-3 font-bold">Mașini asociate</h2>
        <p class="mb-3 text-xs text-stone-500">
            Un carusel de piese setat pe „mașinile articolului” folosește aceste legături ca să
            afișeze piese compatibile.
        </p>

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
                <button wire:click="addVehicle" class="w-full rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white">Leagă</button>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        @forelse($blocks as $index => $block)
            <div class="card-padded">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold">{{ $block['label'] }}</span>
                    <div class="flex items-center gap-2 text-sm">
                        <button wire:click="moveBlock({{ $block['id'] }}, -1)" class="rounded border px-2" title="Mai sus">↑</button>
                        <button wire:click="moveBlock({{ $block['id'] }}, 1)" class="rounded border px-2" title="Mai jos">↓</button>
                        <button wire:click="removeBlock({{ $block['id'] }})" wire:confirm="Ștergi blocul?" class="text-red-600 underline">Șterge</button>
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
            <p class="rounded-xl border border-dashed border-stone-300 p-6 text-sm text-stone-500">
                Articolul nu are încă blocuri. Adaugă primul mai jos.
            </p>
        @endforelse
    </section>

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-stone-200 bg-white p-5">
        <select wire:model="newBlockType" >
            @foreach($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
        </select>
        <button wire:click="addBlock" class="btn-secondary">Adaugă bloc</button>

        <button wire:click="saveBlocks" class="ml-auto rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">
            Salvează blocurile
        </button>
    </div>
</div>
