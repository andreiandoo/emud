@props(['review'])

@php($embed = \App\Support\VideoEmbed::url($review->video_url))
@php($vehicle = $review->vehicleLabel())

<article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white">
    @if($embed)
        <div class="aspect-video bg-stone-950">
            <iframe src="{{ $embed }}" title="{{ $review->title ?? 'Recenzie' }}" loading="lazy"
                    class="h-full w-full" frameborder="0" allowfullscreen></iframe>
        </div>
    @elseif($review->imageUrl())
        <img src="{{ $review->imageUrl() }}" alt="{{ $review->title ?? $review->reviewer_name }}"
             loading="lazy" class="aspect-[4/3] w-full object-cover">
    @endif

    <div class="flex flex-1 flex-col gap-3 p-5">
        @if($review->rating)
            {{-- Drawn as text so it survives with images off and reads correctly to a screen
                 reader, which a row of star glyphs does not. --}}
            <p class="text-sm font-semibold text-amber-600" aria-label="{{ $review->rating }} din 5">
                {{ str_repeat('★', $review->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $review->rating) }}</span>
            </p>
        @endif

        @if($review->title)
            <h3 class="font-bold leading-snug">{{ $review->title }}</h3>
        @endif

        <p class="flex-1 text-sm leading-relaxed text-stone-700">{{ $review->body }}</p>

        <footer class="mt-auto border-t border-stone-100 pt-3">
            <p class="text-sm font-semibold">{{ $review->reviewer_name }}</p>
            <p class="text-xs text-stone-500">
                {{ collect([$vehicle, $review->reviewer_location])->filter()->implode(' · ') }}
            </p>

            @if($review->instagramUrl() || $review->facebookUrl())
                <p class="mt-2 flex flex-wrap gap-3 text-xs font-semibold text-stone-600">
                    @if($review->instagramUrl())
                        <a href="{{ $review->instagramUrl() }}" target="_blank" rel="noopener nofollow" class="hover:text-stone-900 hover:underline">Instagram</a>
                    @endif
                    @if($review->facebookUrl())
                        <a href="{{ $review->facebookUrl() }}" target="_blank" rel="noopener nofollow" class="hover:text-stone-900 hover:underline">Facebook</a>
                    @endif
                </p>
            @endif
        </footer>
    </div>
</article>
