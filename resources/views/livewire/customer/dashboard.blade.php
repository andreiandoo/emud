<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-black tracking-tight">Contul meu</h1>
        <p class="mt-1 text-sm text-stone-600">Bine ai venit, {{ auth()->user()->name }}.</p>
    </div>

    <nav class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('customer.garage') }}" class="rounded-full border border-stone-300 px-3 py-1 hover:border-stone-900">Garajul meu</a>
        <a href="{{ route('customer.orders') }}" class="rounded-full border border-stone-300 px-3 py-1 hover:border-stone-900">Comenzile mele</a>
        <a href="{{ route('customer.profile') }}" class="rounded-full border border-stone-300 px-3 py-1 hover:border-stone-900">Datele mele</a>
    </nav>

    <section class="rounded-xl border border-stone-200 bg-white p-6">
        <div class="mb-3 flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-bold">Garajul meu</h2>
            <a href="{{ route('customer.garage') }}" class="text-sm font-semibold underline">Administrează</a>
        </div>

        @if($vehicles->isEmpty())
            <p class="text-sm text-stone-500">
                Nu ai nicio mașină salvată.
                <a href="{{ route('customer.garage') }}" class="font-semibold underline">Adaugă prima mașină</a>
                ca să filtrăm automat piesele compatibile.
            </p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach($vehicles as $garageVehicle)
                    <li class="flex items-center gap-2">
                        <span class="font-medium">{{ $garageVehicle->make?->name }} {{ $garageVehicle->model?->name }}</span>
                        <span class="text-stone-500">{{ $garageVehicle->year }}</span>
                        @if($garageVehicle->is_primary)
                            <span class="rounded-full bg-lime-100 px-2 py-0.5 text-xs font-semibold text-lime-900">Principală</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if($vehicle)
            <p class="mt-4 border-t border-stone-100 pt-4 text-sm text-stone-600">
                Magazinul filtrează acum pentru <span class="font-semibold text-stone-900">{{ $vehicle->label() }}</span>.
            </p>
        @endif
    </section>

    <form method="post" action="{{ route('customer.logout') }}">
        @csrf
        <button class="text-sm text-stone-600 underline hover:text-stone-900">Ieși din cont</button>
    </form>
</div>
