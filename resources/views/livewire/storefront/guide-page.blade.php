<article class="mx-auto max-w-3xl space-y-6">
    <x-seo :title="$article->seo_title ?? $article->title"
           :description="$article->seo_description ?? $article->excerpt"
           :canonical="$article->canonical_url"
           :index="$article->robots_index"
           :follow="$article->robots_follow"
           type="article" />

    <nav class="text-xs text-stone-500">
        <a href="{{ route('storefront.guides') }}" class="hover:underline">Ghiduri</a>
        <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $article->title }}</span>
    </nav>

    <header class="space-y-2">
        @if($article->category)
            <span class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $article->category->name }}</span>
        @endif
        <h1 class="text-3xl font-black tracking-tight">{{ $article->title }}</h1>
        <p class="text-sm text-stone-500">
            {{ $article->published_at?->format('d.m.Y') }}
            @if($article->author) · {{ $article->author->name }} @endif
        </p>
    </header>

    @if($article->excerpt)
        <p class="text-lg text-stone-700">{{ $article->excerpt }}</p>
    @endif

    @if($article->content)
        <div class="prose prose-stone max-w-none">{!! app(\App\Support\HtmlSanitizer::class)->clean($article->content) !!}</div>
    @endif
</article>
