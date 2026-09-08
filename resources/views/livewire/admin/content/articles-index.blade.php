<div>
    <x-admin.page-header title="Articole & ghiduri" subtitle="Conținut editorial, categorii și SEO.">
        <x-slot:actions>
            <a href="{{ route('admin.articles.create') }}" class="btn-primary">Articol nou</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.panel title="Categorii de articole" subtitle="Grupează ghidurile în magazin și în feedul de conținut."
                   class="mb-8">
        <form wire:submit="saveCategory" class="flex max-w-md gap-2">
            <input wire:model="categoryName" placeholder="Nume categorie">
            <button type="submit" class="btn-primary shrink-0">Salvează</button>
        </form>
        @error('categoryName') <span class="field-error">{{ $message }}</span> @enderror

        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($categories as $category)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 py-1 pl-3 pr-1.5 text-sm"
                          wire:key="article-category-{{ $category->id }}">
                        <button type="button" wire:click="editCategory({{ $category->id }})" class="text-stone-800 hover:text-stone-950">
                            {{ $category->name }} <span class="text-stone-400">{{ $category->articles_count }}</span>
                        </button>
                        <button type="button" wire:click="deleteCategory({{ $category->id }})"
                                wire:confirm="Ștergi categoria? Articolele rămân fără categorie."
                                class="rounded-full px-1 text-stone-400 transition hover:bg-red-100 hover:text-red-700"
                                aria-label="Șterge categoria {{ $category->name }}">×</button>
                    </span>
                @endforeach
            </div>
        @endif
    </x-admin.panel>

    @if($articles->isEmpty())
        <x-admin.empty title="Niciun articol" hint="Ghidurile și articolele publicate apar aici.">
            <a href="{{ route('admin.articles.create') }}" class="btn-primary">Scrie primul articol</a>
        </x-admin.empty>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Titlu</th>
                        <th>Categorie</th>
                        <th>Autor</th>
                        <th>Status</th>
                        <th>Publicare</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($articles as $article)
                        <tr wire:key="article-{{ $article->id }}">
                            <td>
                                <a href="{{ route('admin.articles.edit', $article) }}" class="font-medium text-stone-900 hover:underline">{{ $article->title }}</a>
                            </td>
                            <td class="text-stone-600">{{ $article->category?->name ?? '—' }}</td>
                            <td>
                                @if($article->author)
                                    <div class="flex items-center gap-2.5">
                                        <x-admin.avatar :name="$article->author->name" size="sm" />
                                        <span class="truncate text-stone-600">{{ $article->author->name }}</span>
                                    </div>
                                @else
                                    <span class="text-stone-400">—</span>
                                @endif
                            </td>
                            <td>
                                <x-admin.status :label="$article->status === 'published' ? 'Publicat' : 'Ciornă'"
                                                :tone="$article->status === 'published' ? 'positive' : 'neutral'" />
                            </td>
                            <td class="whitespace-nowrap text-stone-500">{{ $article->published_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>
                                <x-admin.row-actions>
                                    <x-admin.row-action href="{{ route('admin.articles.edit', $article) }}">Editează</x-admin.row-action>
                                </x-admin.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
</div>
