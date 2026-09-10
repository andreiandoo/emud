@props(['review'])

@php($embed = \App\Support\VideoEmbed::url($review->video_url))
@php($vehicle = $review->vehicleLabel())

<article class="flex flex-col overflow-hidden rounded-[3px] border border-line bg-white">
    @if($embed)
        <div class="aspect-video bg-g0">
            <iframe src="{{ $embed }}" title="{{ $review->title ?? 'Recenzie' }}" loading="lazy"
                    class="h-full w-full" frameborder="0" allowfullscreen></iframe>
        </div>
    @elseif($review->imageUrl())
        <img src="{{ $review->imageUrl() }}" alt="{{ $review->title ?? $review->reviewer_name }}"
             loading="lazy" class="aspect-[4/3] w-full object-cover">
    @endif

    <div class="flex flex-1 flex-col gap-3.5 p-6">
        @if($review->rating)
            {{-- Drawn as text so it survives with images off and reads correctly to a screen
                 reader, which a row of star glyphs alone does not. --}}
            <p class="text-sm font-semibold tracking-[.12em] text-signal" aria-label="{{ $review->rating }} din 5">
                {{ str_repeat('★', $review->rating) }}<span class="text-line2">{{ str_repeat('★', 5 - $review->rating) }}</span>
            </p>
        @endif

        @if($review->title)
            <h3 class="font-display text-xl font-semibold leading-snug">{{ $review->title }}</h3>
        @endif

        <p class="flex-1 text-[15px] leading-relaxed text-ink2">{{ $review->body }}</p>

        <footer class="mt-auto flex items-center gap-3 border-t border-line pt-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-light font-semibold">{{ mb_substr($review->reviewer_name, 0, 1) }}</span>
            <div class="min-w-0">
                <p class="text-sm font-semibold">{{ $review->reviewer_name }}</p>
                <p class="truncate text-xs text-ink2">
                    {{ collect([$vehicle, $review->reviewer_location])->filter()->implode(' · ') }}
                </p>
            </div>

            @if($review->instagramUrl() || $review->facebookUrl())
                <p class="ml-auto flex shrink-0 gap-3 text-xs font-semibold text-ink2">
                    @if($review->instagramUrl())
                        <a href="{{ $review->instagramUrl() }}" target="_blank" rel="noopener nofollow" class="hover:text-ink" aria-label="Instagram"><x-storefront.icon name="instagram" class="h-4 w-4" /></a>
                    @endif
                    @if($review->facebookUrl())
                        <a href="{{ $review->facebookUrl() }}" target="_blank" rel="noopener nofollow" class="hover:text-ink" aria-label="Facebook"><x-storefront.icon name="facebook" class="h-4 w-4" /></a>
                    @endif
                </p>
            @endif
        </footer>
    </div>
</article>
