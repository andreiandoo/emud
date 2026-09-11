<article>
    <x-seo :title="$article->seo_title ?? $article->title"
           :description="$article->seo_description ?? $article->excerpt"
           :canonical="$article->canonical_url"
           :index="$article->robots_index"
           :follow="$article->robots_follow"
           type="article" />

    <header class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-scene="dusk" data-seed="{{ $article->id * 7 }}" class="absolute inset-0 -z-20 h-full w-full opacity-60" aria-hidden="true"></canvas>
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-g0 via-g0/70 to-g0/30"></div>

        <div class="shell pb-14 pt-16 sm:pt-24">
            <nav class="flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-[.1em] text-mute" aria-label="Breadcrumb">
                <a href="{{ route('storefront.guides') }}" class="transition hover:text-bone">Ghiduri</a>
                <span aria-hidden="true">/</span>
                <span class="text-bone">{{ $article->title }}</span>
            </nav>

            <div class="mt-8 max-w-4xl">
                @if($article->category)
                    <p class="st-kicker text-mute">{{ $article->category->name }}</p>
                @endif
                <h1 class="st-display mt-5 text-[clamp(2.4rem,5.4vw,5rem)] leading-[.95]">{{ $article->title }}</h1>
                <p class="mt-6 font-mono text-xs uppercase tracking-[.08em] text-mute">
                    {{ $article->published_at?->format('d/m/Y') }}
                    @if($article->author) · {{ $article->author->name }} @endif
                </p>
            </div>
        </div>
    </header>

    <div class="shell grid gap-10 pb-24 pt-12 sm:pt-16">
        <div class="mx-auto grid w-full max-w-3xl gap-8">
            @if($article->excerpt)
                <p class="font-display text-[clamp(1.3rem,2vw,1.6rem)] font-medium leading-snug text-ink">{{ $article->excerpt }}</p>
            @endif

            @if($article->vehicles->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="field-label mb-0">Pentru</span>
                    @foreach($article->vehicles as $link)
                        <span class="st-chip h-8 bg-white">{{ $link->label() }}</span>
                    @endforeach
                </div>
            @endif

            {{-- The legacy HTML body still renders when an article predates the block editor, so
                 nothing written before the migration disappears from the site. --}}
            @if($article->blocks->isEmpty() && $article->content)
                <div class="st-prose">{!! app(\App\Support\HtmlSanitizer::class)->clean($article->content) !!}</div>
            @endif

            @foreach($article->blocks as $block)
                <div>
                    @include('livewire.storefront.partials.article-block', ['block' => $block, 'article' => $article])
                </div>
            @endforeach

            <p class="border-t border-line pt-6">
                <a href="{{ route('storefront.guides') }}" class="st-link"><x-storefront.icon name="arrow-left" /> Toate ghidurile</a>
            </p>
        </div>
    </div>
</article>
