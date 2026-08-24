<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Administrare' }} · eMUD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
<div x-data="{ open: false }" class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
    <aside :class="open ? 'block' : 'hidden'" class="fixed inset-y-0 z-30 w-64 bg-stone-950 p-5 text-stone-100 lg:static lg:block">
        <div class="mb-8 flex items-center justify-between">
            <a href="{{ route('admin.dashboard') }}" class="text-xl font-black tracking-[.18em]">eMUD</a>
            <button @click="open=false" class="lg:hidden">×</button>
        </div>
        <nav class="space-y-1 text-sm">
            @foreach ([
                'admin.dashboard' => 'Panou general',
                'admin.products.index' => 'Produse',
                'admin.categories.index' => 'Categorii',
                'admin.attributes.index' => 'Filtre & atribute',
                'admin.vehicles.index' => 'Compatibilitate auto',
                'admin.suppliers.index' => 'Furnizori',
                'admin.suppliers.sync-runs' => 'Sincronizări',
                'admin.orders.index' => 'Comenzi',
            ] as $route => $label)
                <a href="{{ route($route) }}" @class([
                    'block rounded-lg px-3 py-2.5 transition',
                    'bg-lime-400 font-semibold text-stone-950' => request()->routeIs($route),
                    'text-stone-300 hover:bg-stone-800 hover:text-white' => ! request()->routeIs($route),
                ])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="post" action="{{ route('admin.logout') }}" class="mt-8">
            @csrf
            <button class="text-sm text-stone-400 hover:text-white">Ieșire din cont</button>
        </form>
    </aside>

    <main class="min-w-0">
        <header class="flex h-16 items-center border-b border-stone-200 bg-white px-4 lg:px-8">
            <button @click="open=true" class="mr-4 rounded border px-3 py-1 lg:hidden">Meniu</button>
            <div class="ml-auto text-sm text-stone-500">{{ auth()->user()->name }}</div>
        </header>
        <div class="p-4 lg:p-8">{{ $slot }}</div>
    </main>
</div>
@livewireScripts
</body>
</html>
