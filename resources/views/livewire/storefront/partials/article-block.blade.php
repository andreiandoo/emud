@props(['block', 'article'])

@php($sanitizer = app(\App\Support\HtmlSanitizer::class))

@switch($block->type->value)
    @case('rich_text')
        <div class="st-prose max-w-none">{!! $sanitizer->clean($block->get('html')) !!}</div>
        @break

    @case('image')
        @if($block->get('url'))
            <figure class="grid gap-2.5">
                <img src="{{ $block->get('url') }}" alt="{{ $block->get('alt', '') }}" class="w-full rounded-[3px]" loading="lazy">
                @if($block->get('caption'))
                    <figcaption class="font-mono text-[11px] uppercase tracking-[.06em] text-ink2">{{ $block->get('caption') }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('gallery')
        @php($images = collect($block->get('images', []))->filter(fn ($image) => ! empty($image['url'])))
        @if($images->isNotEmpty())
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($images as $image)
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? '' }}" class="aspect-square w-full rounded-[3px] object-cover" loading="lazy">
                @endforeach
            </div>
        @endif
        @break

    @case('video')
        {{-- Built from the extracted video id, never from the URL the author pasted: passing a
             URL through would let anything at all be framed inside a page customers trust. --}}
        @php($embed = \App\Content\VideoEmbed::fromUrl($block->get('url')))
        @if($embed)
            <figure class="grid gap-2.5">
                <div class="aspect-video overflow-hidden rounded-[3px] bg-g0">
                    <iframe src="{{ $embed->embedUrl }}" class="h-full w-full" loading="lazy"
                            title="{{ $block->get('caption', 'Video') }}"
                            allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                </div>
                @if($block->get('caption'))
                    <figcaption class="font-mono text-[11px] uppercase tracking-[.06em] text-ink2">{{ $block->get('caption') }}</figcaption>
                @endif
            </figure>
        @else
            <p class="rounded-[3px] border border-dashed border-line2 bg-white p-4 text-sm text-ink2">
                Videoclipul nu a putut fi încărcat. Sunt acceptate doar linkuri YouTube și Vimeo.
            </p>
        @endif
        @break

    @case('callout')
        <div @class([
            'grid gap-2 rounded-[3px] border-l-2 p-5',
            'border-amber-500 bg-amber-50' => $block->get('tone') === 'warning',
            'border-red-600 bg-red-50' => $block->get('tone') === 'danger',
            'border-signal bg-sand/60' => ! in_array($block->get('tone'), ['warning', 'danger'], true),
        ])>
            @if($block->get('title'))<p class="font-display text-lg font-semibold">{{ $block->get('title') }}</p>@endif
            <div class="st-prose max-w-none text-[15px]">{!! $sanitizer->clean($block->get('html')) !!}</div>
        </div>
        @break

    @case('steps')
        @php($steps = collect($block->get('steps', []))->filter(fn ($step) => ! empty($step['text'])))
        @if($steps->isNotEmpty())
            <ol class="grid gap-0 border-t border-line">
                @foreach($steps as $index => $step)
                    <li class="grid grid-cols-[3.5rem_1fr] gap-4 border-b border-line py-5">
                        <span class="font-display text-3xl font-semibold leading-none text-signal">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="grid gap-1">
                            @if(! empty($step['title']))<p class="font-display text-lg font-semibold">{{ $step['title'] }}</p>@endif
                            <p class="text-ink2">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
        @break

    @case('parts_carousel')
        @php($products = app(\App\Content\PartsCarousel::class)->resolve($block, $article))
        @if($products->isNotEmpty())
            <section class="grid gap-5 rounded-[3px] bg-g0 p-6 text-bone">
                <h2 class="font-display text-2xl font-semibold">{{ $block->get('title', 'Piese recomandate') }}</h2>
                {{-- Horizontally scrollable rather than a wrapping grid: an article column is narrow,
                     and a carousel that reflows into four rows stops being a carousel. --}}
                <div class="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2 [scrollbar-width:none]">
                    @foreach($products as $product)
                        <div class="w-60 shrink-0 snap-start">
                            @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => null])
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @break
@endswitch
