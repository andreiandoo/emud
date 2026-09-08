<div class="space-y-6">
    <x-seo title="Ghiduri și articole"
           description="Ghiduri practice pentru 4x4, off-road, mudding și overlanding: montaj, alegerea pieselor și pregătirea mașinii." />

    <div>
        <h1 class="text-2xl font-black tracking-tight">Ghiduri și articole</h1>
        <p class="mt-1 text-sm text-stone-600">Sfaturi practice pentru pregătirea și întreținerea mașinii tale de teren.</p>
    </div>

    @if($categories->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('category', '')" @class([
                'rounded-full border px-3 py-1 text-sm',
                'border-stone-900 bg-stone-900 text-white' => $category === '',
                'border-stone-300 hover:border-stone-900' => $category !== '',
            ])>Toate</button>
            @foreach($categories as $articleCategory)
                <button wire:click="$set('category', '{{ $articleCategory->slug }}')" @class([
                    'rounded-full border px-3 py-1 text-sm',
                    'border-stone-900 bg-stone-900 text-white' => $category === $articleCategory->slug,
                    'border-stone-300 hover:border-stone-900' => $category !== $articleCategory->slug,
                ])>{{ $articleCategory->name }}</button>
            @endforeach
        </div>
    @endif

    @if($articles->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Nu există încă articole publicate.
        </p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($articles as $article)
                <a href="{{ route('storefront.guide', $article->slug) }}" class="flex flex-col rounded-xl border border-stone-200 bg-white p-4 transition hover:border-stone-400">
                    @if($article->category)
                        <span class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $article->category->name }}</span>
                    @endif
                    <span class="mt-1 font-semibold">{{ $article->title }}</span>
                    @if($article->excerpt)
                        <span class="mt-2 text-sm text-stone-600">{{ Str::limit($article->excerpt, 120) }}</span>
                    @endif
                    <span class="mt-auto pt-3 text-xs text-stone-400">{{ $article->published_at?->format('d.m.Y') }}</span>
                </a>
            @endforeach
        </div>

        <div>{{ $articles->links() }}</div>
    @endif
</div>
