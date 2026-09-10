@props(['title', 'active', 'intro' => null, 'kicker' => 'Contul meu'])

{{-- The frame every account page shares: a graphite band with the page title and the tabs,
     then the page on the light ground. The tabs are the account's navigation, so they are
     here once rather than repeated as a row of pills on each page. --}}
@php($tabs = [
    ['customer.dashboard', 'Prezentare', 'grid', 'dashboard'],
    ['customer.garage', 'Garajul meu', 'car', 'garage'],
    ['customer.orders', 'Comenzi', 'box', 'orders'],
    ['customer.appointments', 'Programări', 'calendar', 'appointments'],
    ['customer.favourites', 'Favorite', 'heart', 'favourites'],
    ['customer.profile', 'Datele mele', 'user', 'profile'],
])

<div>
    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.05)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell pt-12 sm:pt-16">
            <p class="st-kicker text-mute">{{ $kicker }}</p>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-6">
                <h1 class="st-display text-[clamp(2.25rem,4.4vw,4.25rem)]">{{ $title }}</h1>

                @isset($actions)
                    <div class="flex flex-wrap gap-2">{{ $actions }}</div>
                @endisset
            </div>

            @if($intro)
                <p class="mt-4 max-w-[60ch] text-mute">{{ $intro }}</p>
            @endif

            <nav class="-mx-1 mt-10 flex gap-1 overflow-x-auto [scrollbar-width:none]" aria-label="Contul meu">
                @foreach($tabs as [$route, $label, $icon, $key])
                    @php($current = $key === $active)
                    <a href="{{ route($route) }}" @if($current) aria-current="page" @endif @class([
                        'flex shrink-0 items-center gap-2 border-b-2 px-4 pb-4 pt-2 text-sm font-medium transition',
                        'border-signal text-bone' => $current,
                        'border-transparent text-mute hover:text-bone' => ! $current,
                    ])>
                        <x-storefront.icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-14">
        {{ $slot }}
    </div>
</div>
