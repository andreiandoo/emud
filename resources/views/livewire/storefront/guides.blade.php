@php($scenes = ['mud', 'sand', 'forest', 'steel', 'dusk', 'snow'])

<div>
    <x-seo title="Ghiduri și articole"
           description="Ghiduri practice pentru 4x4, off-road, mudding și overlanding: montaj, alegerea pieselor și pregătirea mașinii." />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pb-12 pt-16 sm:pt-24">
            <p class="st-kicker text-mute">News & events</p>
            <h1 class="st-display mt-5 max-w-[14ch] text-[clamp(2.6rem,6vw,5.75rem)] leading-[.92]">Ghiduri și articole</h1>
            <p class="mt-5 max-w-[56ch] text-[clamp(1rem,1.2vw,1.15rem)] text-[#cfcdc6]">Sfaturi practice pentru pregătirea și întreținerea mașinii tale de teren.</p>

            @if($categories->isNotEmpty())
                <div class="mt-10 flex flex-wrap gap-2">
                    <button wire:click="$set('category', '')" @class([
                        'st-chip',
                        'border-bone bg-bone text-ink' => $category === '',
                        'text-bone hover:border-bone' => $category !== '',
                    ])>Toate</button>
                    @foreach($categories as $articleCategory)
                        <button wire:click="$set('category', '{{ $articleCategory->slug }}')" @class([
                            'st-chip',
                            'border-bone bg-bone text-ink' => $category === $articleCategory->slug,
                            'text-bone hover:border-bone' => $category !== $articleCategory->slug,
                        ])>{{ $articleCategory->name }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="shell pb-24 pt-12 sm:pt-16">
        @if($articles->isEmpty())
            <p class="rounded-[3px] border border-dashed border-line2 bg-white p-10 text-center text-ink2">
                Nu există încă articole publicate.
            </p>
        @else
            <div class="grid gap-x-6 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($articles as $article)
                    <a href="{{ route('storefront.guide', $article->slug) }}" class="group grid content-start gap-4" wire:key="article-{{ $article->id }}">
                        <div class="st-tile aspect-[16/11] bg-g2">
                            <canvas class="st-media" data-st-scene="{{ $scenes[$article->id % count($scenes)] }}" data-seed="{{ $article->id * 7 }}" aria-hidden="true"></canvas>
                        </div>

                        <div class="flex gap-3.5 font-mono text-[12px] uppercase tracking-[.06em] text-ink2">
                            @if($article->category)<span>{{ $article->category->name }}</span>@endif
                            <span>{{ $article->published_at?->format('d/m/Y') }}</span>
                        </div>

                        <h2 class="font-display text-[1.45rem] font-semibold leading-tight tracking-[-.015em] text-balance transition group-hover:text-signal">{{ $article->title }}</h2>

                        @if($article->excerpt)
                            <p class="text-[15px] text-ink2">{{ Str::limit($article->excerpt, 120) }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-12">{{ $articles->links() }}</div>
        @endif
    </div>
</div>
