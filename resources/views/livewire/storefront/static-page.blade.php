<div>
    <x-seo :title="$page->seo_title ?? $page->title"
           :description="$page->seo_description ?? $page->excerpt"
           :canonical="$page->canonical_url"
           :index="$page->robots_index"
           :follow="$page->robots_follow"
           type="article" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-14 sm:pt-20">
            <h1 class="st-display max-w-4xl text-[clamp(2.4rem,5vw,4.5rem)] leading-[.95]">{{ $page->title }}</h1>

            @if($page->version || $page->effective_from)
                <p class="mt-5 font-mono text-xs uppercase tracking-[.08em] text-mute">
                    @if($page->version) Versiunea {{ $page->version }} @endif
                    @if($page->effective_from) · în vigoare din {{ $page->effective_from->format('d/m/Y') }} @endif
                </p>
            @endif
        </div>
    </section>

    <div class="shell pb-24 pt-12 sm:pt-16">
        @if($page->content)
            <div class="st-prose mx-auto">{!! app(\App\Support\HtmlSanitizer::class)->clean($page->content) !!}</div>
        @else
            <p class="mx-auto max-w-3xl rounded-[3px] border border-dashed border-line2 bg-white p-8 text-ink2">
                Conținutul acestei pagini nu a fost încă redactat.
            </p>
        @endif
    </div>
</div>
