<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Piese și accesorii 4x4' }} · eMUD</title>
    @stack('meta')
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
<header class="border-b border-stone-200 bg-white">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4">
        <a href="{{ route('storefront.home') }}" class="text-xl font-black tracking-[.18em]">eMUD</a>

        @php($vehicle = app(\App\Storefront\VehicleContext::class)->current())
        @if($vehicle)
            <span class="hidden items-center gap-2 rounded-full bg-lime-100 px-3 py-1 text-xs font-semibold text-lime-900 sm:inline-flex">
                {{ $vehicle->label() }}
                @if($vehicle->isFromGarage())<span class="font-normal text-lime-700">· din garaj</span>@endif
            </span>
        @endif

        <form action="{{ route('storefront.search') }}" method="get" class="hidden flex-1 md:block">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Caută cod piesă, MPN sau denumire"
                   class="w-full max-w-md rounded-lg border-stone-300 text-sm">
        </form>

        <nav class="ml-auto flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('customer.favourites') }}" class="text-stone-600 hover:text-stone-900">Favorite</a>
                <a href="{{ route('customer.garage') }}" class="text-stone-600 hover:text-stone-900">Garajul meu</a>
                <a href="{{ route('customer.dashboard') }}" class="font-semibold">{{ auth()->user()->name }}</a>
            @else
                <a href="{{ route('customer.login') }}" class="text-stone-600 hover:text-stone-900">Autentificare</a>
                <a href="{{ route('customer.register') }}" class="rounded-lg bg-stone-900 px-3 py-1.5 font-semibold text-white">Cont nou</a>
            @endauth

            @php($cartCount = (int) (app(\App\Storefront\CartManager::class)->current()?->items()->sum('quantity') ?? 0))
            <a href="{{ route('storefront.cart') }}" class="relative whitespace-nowrap text-stone-600 hover:text-stone-900">
                Coș
                @if($cartCount > 0)
                    <span class="ml-1 rounded-full bg-stone-900 px-1.5 py-0.5 text-xs font-bold text-white">{{ $cartCount }}</span>
                @endif
            </a>
        </nav>
    </div>

    @php($menu = \App\Models\Category::query()->whereNull('parent_id')->where('is_active', true)->where('is_visible_in_menu', true)->orderBy('position')->orderBy('name')->get())
    <nav class="border-t border-stone-100">
            <div class="mx-auto flex max-w-6xl gap-5 overflow-x-auto px-4 py-2.5 text-sm">
                @foreach($menu as $item)
                    <a href="{{ route('storefront.category', $item) }}" class="whitespace-nowrap text-stone-600 hover:text-stone-900">{{ $item->name }}</a>
                @endforeach
                <a href="{{ route('storefront.guides') }}" class="whitespace-nowrap text-stone-600 hover:text-stone-900">Ghiduri</a>
                <a href="{{ route('storefront.contact') }}" class="whitespace-nowrap text-stone-600 hover:text-stone-900">Contact</a>
            </div>
    </nav>
</header>

<main class="mx-auto max-w-6xl px-4 py-8">{{ $slot }}</main>

<footer class="mt-16 border-t border-stone-200 bg-white">
    <div class="mx-auto max-w-6xl space-y-4 px-4 py-8 text-xs text-stone-500">
        @php($footerPages = \App\Models\Page::query()->published()->where('show_in_footer', true)->orderBy('position')->orderBy('title')->get())
        @if($footerPages->isNotEmpty())
            <nav class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($footerPages as $footerPage)
                    <a href="{{ route('storefront.page', $footerPage->slug) }}" class="hover:text-stone-900 hover:underline">{{ $footerPage->title }}</a>
                @endforeach
                <a href="{{ route('storefront.contact') }}" class="hover:text-stone-900 hover:underline">Contact</a>
            </nav>
        @endif
        <p>eMUD · piese și accesorii 4x4, off-road și overlanding</p>
    </div>
</footer>
@livewireScripts
</body>
</html>
