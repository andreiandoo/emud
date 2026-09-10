@props(['title', 'intro' => null, 'kicker' => 'Contul eMUD'])

{{-- Sign-in, sign-up and the password pages share this: the reason to have an account on one
     side, the form on the other. On a phone only the form is left. --}}
<div class="grid min-h-[calc(100svh-var(--st-header-h))] lg:grid-cols-[1.05fr_1fr]">
    <aside class="relative isolate hidden overflow-hidden bg-g0 text-bone lg:flex lg:flex-col lg:justify-end">
        <canvas data-st-scene="dusk" data-seed="61" class="absolute inset-0 -z-20 h-full w-full" aria-hidden="true"></canvas>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-g0 via-g0/50 to-transparent"></div>

        <div class="grid gap-8 p-12 xl:p-16">
            <p class="st-kicker text-mute">De ce ai nevoie de cont</p>
            <h2 class="st-display max-w-[16ch] text-[clamp(2.25rem,3.6vw,3.75rem)]">Garajul tău, comenzile tale, montajul programat.</h2>

            <ul class="grid max-w-md gap-4 text-[15px] text-[#cfcdc6]">
                @foreach([
                    ['car', 'Salvezi mașinile, iar magazinul îți arată doar ce li se potrivește.'],
                    ['box', 'Urmărești comenzile și piesele cumpărate pentru fiecare mașină.'],
                    ['calendar', 'Ceri și urmărești programări la service-urile partenere.'],
                ] as [$icon, $text])
                    <li class="flex items-start gap-3.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-gl2 text-sand">
                            <x-storefront.icon :name="$icon" class="h-4 w-4" />
                        </span>
                        <span class="pt-1.5">{{ $text }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>

    <div class="grid place-items-center bg-light px-5 py-14 sm:px-10">
        <div class="w-full max-w-md">
            <p class="st-kicker text-ink2">{{ $kicker }}</p>
            <h1 class="st-display mt-4 text-[clamp(2.25rem,4vw,3.25rem)]">{{ $title }}</h1>
            @if($intro)
                <p class="mt-3 text-ink2">{{ $intro }}</p>
            @endif

            <div class="mt-9">{{ $slot }}</div>
        </div>
    </div>
</div>
