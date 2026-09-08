<div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
    <aside class="space-y-3">
        <div class="flex items-center justify-between">
            <h1 class="text-base font-semibold">Pagini</h1>
            <button wire:click="create" class="btn-primary">Pagină nouă</button>
        </div>

        <ul class="space-y-1">
            @foreach($pages as $page)
                <li>
                    <button wire:click="edit({{ $page->id }})" @class([
                        'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm',
                        'bg-stone-900 text-white' => $editingId === $page->id,
                        'hover:bg-stone-100' => $editingId !== $page->id,
                    ])>
                        <span class="min-w-0 flex-1 truncate">{{ $page->title }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-900' => $page->status === 'published',
                            'bg-stone-200 text-stone-600' => $page->status !== 'published',
                        ])>{{ $page->status === 'published' ? 'publicată' : 'ciornă' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </aside>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-stone-200 bg-white p-6">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-base font-semibold">{{ $editingId ? 'Editează pagina' : 'Pagină nouă' }}</h2>
            @if($saved)<span class="text-sm font-semibold text-emerald-700">{{ $saved }}</span>@endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Titlu</span>
                <input type="text" wire:model.live.debounce.500ms="title" >
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="field-label">Slug (URL)</span>
                <input type="text" wire:model="slug" >
                @error('slug') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="field-label">Rezumat</span>
            <textarea wire:model="excerpt" rows="2" ></textarea>
            @error('excerpt') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="field-label">Conținut (HTML)</span>
            <textarea wire:model="content" rows="16" class="font-mono text-xs"></textarea>
            <span class="field-hint">
                HTML-ul este filtrat la afișare printr-o listă de etichete permise. Scripturile și atributele de eveniment sunt eliminate.
            </span>
            @error('content') <span class="field-error">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block">
                <span class="field-label">Stare</span>
                <select wire:model="status" >
                    <option value="draft">Ciornă</option>
                    <option value="published">Publicată</option>
                </select>
            </label>
            <label class="block">
                <span class="field-label">Versiune</span>
                <input type="text" wire:model="version" placeholder="1.0" >
            </label>
            <label class="block">
                <span class="field-label">În vigoare din</span>
                <input type="date" wire:model="effective_from" >
            </label>
            <label class="block">
                <span class="field-label">Poziție</span>
                <input type="number" wire:model="position" >
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">SEO title</span>
                <input type="text" wire:model="seo_title" >
            </label>
            <label class="block">
                <span class="field-label">SEO description</span>
                <input type="text" wire:model="seo_description" >
            </label>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="show_in_footer" class="rounded border-stone-300"> Afișează în footer</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="robots_index" class="rounded border-stone-300"> Permite indexarea</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="robots_follow" class="rounded border-stone-300"> Permite urmărirea linkurilor</label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Salvează</button>
            @if($editingId && $status === 'published')
                <a href="{{ route('storefront.page', $slug) }}" target="_blank" class="text-sm underline">Vezi pagina publicată</a>
            @endif
        </div>
    </form>
</div>
