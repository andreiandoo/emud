<!doctype html>
<html lang="ro" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Administrare' }} · eMUD</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="admin-surface h-full bg-stone-100 text-stone-900 antialiased">

@php($groups = \App\Support\AdminNavigation::groups())
@php($footerItems = \App\Support\AdminNavigation::footerItems())

<div x-data="{ mobile: false }" class="flex min-h-full">

    {{-- Sidebar. Fixed on desktop, slide-over on mobile: an operator on a phone is usually
         checking one thing, not navigating, so it stays out of the way until asked for. --}}
    <div x-show="mobile" x-cloak @click="mobile = false" class="fixed inset-0 z-30 bg-stone-950/50 lg:hidden"></div>

    <aside :class="mobile ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-stone-950 text-stone-300 transition-transform lg:static lg:translate-x-0">

        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-black tracking-[.2em] text-white">eMUD</a>
            <button @click="mobile = false" class="text-stone-400 lg:hidden" aria-label="Închide meniul">✕</button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
            @foreach($groups as $group)
                <div>
                    <p class="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-stone-500">
                        {{ $group['label'] }}
                    </p>

                    <ul class="space-y-0.5">
                        @foreach($group['items'] as $item)
                            @php($current = request()->routeIs($item['route']))
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @if($current) aria-current="page" @endif
                                   @class([
                                       'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition',
                                       'bg-stone-800 font-semibold text-white' => $current,
                                       'text-stone-400 hover:bg-stone-900 hover:text-white' => ! $current,
                                   ])>
                                    <x-admin.icon :name="$item['icon']" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-stone-800 px-3 py-3">
            @foreach($footerItems as $item)
                @php($current = request()->routeIs($item['route']))
                <a href="{{ route($item['route']) }}" @class([
                    'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition',
                    'bg-stone-800 font-semibold text-white' => $current,
                    'text-stone-400 hover:bg-stone-900 hover:text-white' => ! $current,
                ])>
                    <x-admin.icon :name="$item['icon']" />
                    {{ $item['label'] }}
                </a>
            @endforeach

            <div class="mt-2 flex items-center gap-3 rounded-lg px-3 py-2">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-stone-800 text-xs font-bold text-white">
                    {{ Str::of(auth()->user()->name)->explode(' ')->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('') }}
                </span>
                <span class="min-w-0 flex-1 truncate text-sm text-stone-300">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="text-stone-500 hover:text-white" title="Ieșire din cont" aria-label="Ieșire din cont">
                        <x-admin.icon name="logout" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 shrink-0 items-center gap-4 px-4 lg:px-8">
            <button @click="mobile = true" class="btn-secondary lg:hidden" aria-label="Deschide meniul">Meniu</button>
            <a href="{{ route('storefront.home') }}" target="_blank" class="ml-auto text-sm text-stone-500 hover:text-stone-900">
                Vezi magazinul ↗
            </a>
        </header>

        <main class="min-w-0 flex-1 px-4 pb-12 lg:px-8">{{ $slot }}</main>
    </div>
</div>

@livewireScripts
</body>
</html>
