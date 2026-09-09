@props(['shop'])

@php($tier = $shop->effectiveTier())
@php($schedule = $shop->schedule())
@php($cheapest = $shop->relationLoaded('services') ? $shop->services->pluck('pivot.price_from')->filter()->min() : null)

{{-- One card shape for the directory, the city page and the service page, so a workshop reads
     the same wherever it is listed. --}}
<article @class([
    'rounded-xl border bg-white p-5 transition',
    'border-stone-900' => $tier->isPaid(),
    'border-stone-200 hover:border-stone-400' => ! $tier->isPaid(),
])>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ $shop->url() }}" class="text-lg font-semibold text-stone-900 hover:underline">{{ $shop->name }}</a>

            <p class="mt-0.5 text-sm text-stone-600">
                {{ collect([$shop->address, $shop->city, $shop->county])->filter()->implode(', ') }}
            </p>
        </div>

        {{-- Disclosed, not implied by position: ranking that money influenced has to be visible
             to the reader on the card itself. --}}
        @if($tier->isPaid())
            <span class="pill-warning shrink-0">{{ $tier->label() }}</span>
        @endif
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm">
        @unless($schedule->isEmpty())
            @if($schedule->isOpenAt())
                <span class="font-semibold text-emerald-700">Deschis acum</span>
            @else
                <span class="text-stone-500">Închis acum</span>
            @endif
        @endunless

        @if($cheapest !== null)
            <span class="text-stone-600">Lucrări de la {{ \App\Support\Money::of($cheapest, config('emud.catalog.default_currency', 'RON'))->format() }}</span>
        @endif

        @if($shop->fits_parts_bought_here)
            <span class="font-semibold text-stone-900">montează piesele noastre</span>
        @endif

        @if($shop->accepts_appointments)
            <a href="{{ $shop->url() }}#programare" class="ml-auto font-semibold text-stone-900 underline underline-offset-4">Cere o programare</a>
        @endif
    </div>

    @if($shop->specialityList())
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach(array_slice($shop->specialityList(), 0, 6) as $speciality)
                <span class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs text-stone-600">{{ $speciality }}</span>
            @endforeach
        </div>
    @endif
</article>
