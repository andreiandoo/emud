<div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
    <aside class="space-y-3">
        <div class="flex items-center justify-between">
            <h1 class="text-base font-semibold">Pagini</h1>
            <button wire:click="create" class="rounded-lg bg-stone-900 px-3 py-1.5 text-sm font-semibold text-white">Pagină nouă</button>
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
                            'bg-lime-100 text-lime-900' => $page->status === 'published',
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
            @if($saved)<span class="text-sm font-semibold text-lime-700">{{ $saved }}</span>@endif
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Titlu</span>
                <input type="text" wire:model.live.debounce.500ms="title" class="w-full rounded-lg border-stone-300 text-sm">
                @error('title') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Slug (URL)</span>
                <input type="text" wire:model="slug" class="w-full rounded-lg border-stone-300 text-sm">
                @error('slug') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Rezumat</span>
            <textarea wire:model="excerpt" rows="2" class="w-full rounded-lg border-stone-300 text-sm"></textarea>
            @error('excerpt') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-medium text-stone-600">Conținut (HTML)</span>
            <textarea wire:model="content" rows="16" class="w-full rounded-lg border-stone-300 font-mono text-xs"></textarea>
            <span class="mt-1 block text-xs text-stone-500">
                HTML-ul este filtrat la afișare printr-o listă de etichete permise. Scripturile și atributele de eveniment sunt eliminate.
            </span>
            @error('content') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Stare</span>
                <select wire:model="status" class="w-full rounded-lg border-stone-300 text-sm">
                    <option value="draft">Ciornă</option>
                    <option value="published">Publicată</option>
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Versiune</span>
                <input type="text" wire:model="version" placeholder="1.0" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">În vigoare din</span>
                <input type="date" wire:model="effective_from" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Poziție</span>
                <input type="number" wire:model="position" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">SEO title</span>
                <input type="text" wire:model="seo_title" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">SEO description</span>
                <input type="text" wire:model="seo_description" class="w-full rounded-lg border-stone-300 text-sm">
            </label>
        </div>

        <div class="flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="show_in_footer" class="rounded border-stone-300"> Afișează în footer</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="robots_index" class="rounded border-stone-300"> Permite indexarea</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="robots_follow" class="rounded border-stone-300"> Permite urmărirea linkurilor</label>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">Salvează</button>
            @if($editingId && $status === 'published')
                <a href="{{ route('storefront.page', $slug) }}" target="_blank" class="text-sm underline">Vezi pagina publicată</a>
            @endif
        </div>
    </form>
</div>
