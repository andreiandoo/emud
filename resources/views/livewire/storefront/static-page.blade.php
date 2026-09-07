<div class="mx-auto max-w-3xl space-y-6">
    <x-seo :title="$page->seo_title ?? $page->title"
           :description="$page->seo_description ?? $page->excerpt"
           :canonical="$page->canonical_url"
           :index="$page->robots_index"
           :follow="$page->robots_follow"
           type="article" />

    <h1 class="text-3xl font-black tracking-tight">{{ $page->title }}</h1>

    @if($page->version || $page->effective_from)
        <p class="text-xs text-stone-500">
            @if($page->version) Versiunea {{ $page->version }} @endif
            @if($page->effective_from) · în vigoare din {{ $page->effective_from->format('d.m.Y') }} @endif
        </p>
    @endif

    @if($page->content)
        <div class="prose prose-stone max-w-none">{!! app(\App\Support\HtmlSanitizer::class)->clean($page->content) !!}</div>
    @else
        <p class="rounded-xl border border-dashed border-stone-300 p-6 text-sm text-stone-500">
            Conținutul acestei pagini nu a fost încă redactat.
        </p>
    @endif
</div>
