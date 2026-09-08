@props(['block', 'article'])

@php($sanitizer = app(\App\Support\HtmlSanitizer::class))

@switch($block->type->value)
    @case('rich_text')
        <div class="prose prose-stone max-w-none">{!! $sanitizer->clean($block->get('html')) !!}</div>
        @break

    @case('image')
        @if($block->get('url'))
            <figure>
                <img src="{{ $block->get('url') }}" alt="{{ $block->get('alt', '') }}" class="w-full rounded-xl" loading="lazy">
                @if($block->get('caption'))
                    <figcaption class="mt-2 text-xs text-stone-500">{{ $block->get('caption') }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('gallery')
        @php($images = collect($block->get('images', []))->filter(fn ($image) => ! empty($image['url'])))
        @if($images->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($images as $image)
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? '' }}" class="aspect-square w-full rounded-lg object-cover" loading="lazy">
                @endforeach
            </div>
        @endif
        @break

    @case('video')
        {{-- Built from the extracted video id, never from the URL the author pasted: passing a
             URL through would let anything at all be framed inside a page customers trust. --}}
        @php($embed = \App\Content\VideoEmbed::fromUrl($block->get('url')))
        @if($embed)
            <figure>
                <div class="aspect-video overflow-hidden rounded-xl bg-stone-900">
                    <iframe src="{{ $embed->embedUrl }}" class="h-full w-full" loading="lazy"
                            title="{{ $block->get('caption', 'Video') }}"
                            allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                </div>
                @if($block->get('caption'))
                    <figcaption class="mt-2 text-xs text-stone-500">{{ $block->get('caption') }}</figcaption>
                @endif
            </figure>
        @else
            <p class="rounded-lg border border-dashed border-stone-300 p-4 text-sm text-stone-500">
                Videoclipul nu a putut fi încărcat. Sunt acceptate doar linkuri YouTube și Vimeo.
            </p>
        @endif
        @break

    @case('callout')
        <div @class([
            'rounded-xl border p-4',
            'border-amber-300 bg-amber-50' => $block->get('tone') === 'warning',
            'border-red-300 bg-red-50' => $block->get('tone') === 'danger',
            'border-stone-300 bg-stone-50' => ! in_array($block->get('tone'), ['warning', 'danger'], true),
        ])>
            @if($block->get('title'))<p class="font-semibold">{{ $block->get('title') }}</p>@endif
            <div class="prose prose-sm prose-stone max-w-none">{!! $sanitizer->clean($block->get('html')) !!}</div>
        </div>
        @break

    @case('steps')
        @php($steps = collect($block->get('steps', []))->filter(fn ($step) => ! empty($step['text'])))
        @if($steps->isNotEmpty())
            <ol class="space-y-4">
                @foreach($steps as $index => $step)
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-stone-900 text-sm font-bold text-white">{{ $index + 1 }}</span>
                        <div>
                            @if(! empty($step['title']))<p class="font-semibold">{{ $step['title'] }}</p>@endif
                            <p class="text-stone-700">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
        @break

    @case('parts_carousel')
        @php($products = app(\App\Content\PartsCarousel::class)->resolve($block, $article))
        @if($products->isNotEmpty())
            <section class="rounded-xl border border-stone-200 bg-stone-50 p-5">
                <h2 class="mb-4 text-lg font-bold">{{ $block->get('title', 'Piese recomandate') }}</h2>
                {{-- Horizontally scrollable rather than a wrapping grid: an article column is
                     narrow, and a carousel that reflows into four rows stops being a carousel. --}}
                <div class="-mx-1 flex snap-x gap-4 overflow-x-auto px-1 pb-2">
                    @foreach($products as $product)
                        <div class="w-56 shrink-0 snap-start">
                            @include('livewire.storefront.partials.product-card', ['product' => $product, 'verdict' => null])
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @break
@endswitch
