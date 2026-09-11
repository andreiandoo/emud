@props(['shop'])

@php($tier = $shop->effectiveTier())
@php($schedule = $shop->schedule())
@php($cheapest = $shop->relationLoaded('services') ? $shop->services->pluck('pivot.price_from')->filter()->min() : null)

{{-- One card shape for the directory, the city page and the service page, so a workshop reads
     the same wherever it is listed. --}}
<article @class([
    'group grid gap-4 rounded-[3px] border bg-white p-5 transition sm:p-6',
    'border-ink' => $tier->isPaid(),
    'border-line hover:border-line2' => ! $tier->isPaid(),
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ $shop->url() }}" class="font-display text-xl font-semibold leading-tight tracking-[-.01em] text-ink hover:underline">{{ $shop->name }}</a>

            <p class="mt-1 flex items-start gap-1.5 text-sm text-ink2">
                <x-storefront.icon name="pin" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {{ $shop->fullAddress() }}
            </p>
        </div>

        {{-- Disclosed, not implied by position: ranking that money influenced has to be visible
             to the reader on the card itself. --}}
        @if($tier->isPaid())
            <span class="pill-warning shrink-0">{{ $tier->label() }}</span>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
        @unless($schedule->isEmpty())
            @if($schedule->isOpenAt())
                <span class="inline-flex items-center gap-2 font-semibold text-fit"><span class="h-2 w-2 rounded-full bg-fit-bright"></span> Deschis acum</span>
            @else
                <span class="inline-flex items-center gap-2 text-ink2"><span class="h-2 w-2 rounded-full bg-line2"></span> Închis acum</span>
            @endif
        @endunless

        @if($cheapest !== null)
            <span class="text-ink2">Lucrări de la <span class="font-semibold text-ink">{{ \App\Support\Money::of($cheapest, config('emud.catalog.default_currency', 'RON'))->format() }}</span></span>
        @endif

        @if($shop->fits_parts_bought_here)
            <span class="inline-flex items-center gap-1.5 font-semibold text-ink"><x-storefront.icon name="wrench" class="h-4 w-4 text-signal" /> montează piesele noastre</span>
        @endif
    </div>

    @if($shop->specialityList())
        <div class="flex flex-wrap gap-1.5">
            @foreach(array_slice($shop->specialityList(), 0, 6) as $speciality)
                <span class="rounded-full bg-light px-2.5 py-1 text-xs text-ink2">{{ $speciality }}</span>
            @endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
        <a href="{{ $shop->url() }}" class="st-link">Vezi service-ul <x-storefront.icon name="arrow-right" /></a>

        @if($shop->accepts_appointments)
            <a href="{{ $shop->url() }}#programare" class="st-btn st-btn--ink st-btn--sm ml-auto">Cere o programare</a>
        @endif
    </div>
</article>
