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

    <div x-show="mobile" x-cloak @click="mobile = false" class="fixed inset-0 z-30 bg-stone-950/50 lg:hidden"></div>

    <aside :class="mobile ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-stone-950 text-stone-300 transition-transform lg:static lg:translate-x-0">

        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('admin.dashboard') }}" class="text-lg font-black tracking-[.2em] text-white">eMUD</a>
            <button @click="mobile = false" class="text-stone-400 lg:hidden" aria-label="Închide meniul">✕</button>
        </div>

        {{-- Collapse state is kept in localStorage rather than the session: it is a per-device
             preference about screen space, and syncing it across a laptop and a phone would get
             it wrong on one of them. Groups default to open, so a new operator sees everything. --}}
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-6"
             x-data="{
                 state: (() => { try { return JSON.parse(localStorage.getItem('emud.nav.groups')) ?? {} } catch (e) { return {} } })(),
                 isOpen(key) { return this.state[key] ?? true },
                 toggle(key) {
                     this.state = { ...this.state, [key]: ! this.isOpen(key) };
                     try { localStorage.setItem('emud.nav.groups', JSON.stringify(this.state)) } catch (e) {}
                 },
             }">
            @foreach($groups as $group)
                @php($key = Str::slug($group['label']))
                <div>
                    <button type="button" @click="toggle('{{ $key }}')"
                            :aria-expanded="isOpen('{{ $key }}') ? 'true' : 'false'"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-stone-500 transition hover:text-stone-300">
                        <span>{{ $group['label'] }}</span>
                        <span class="text-stone-600 transition-transform"
                              :class="isOpen('{{ $key }}') ? 'rotate-0' : '-rotate-90'" aria-hidden="true">▾</span>
                    </button>

                    <ul x-show="isOpen('{{ $key }}')" x-transition.opacity.duration.150ms class="space-y-0.5 pb-2">
                        @foreach($group['items'] as $item)
                            @php($current = request()->routeIs($item['route']))
                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @if($current) aria-current="page" @endif
                                   @class([
                                       'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition',
                                       'bg-stone-800 font-medium text-white' => $current,
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
                    'bg-stone-800 font-medium text-white' => $current,
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
