@php($firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' '))
@php($primary = $vehicles->firstWhere('is_primary', true) ?? $vehicles->first())

<x-storefront.account active="dashboard" :title="'Salut, '.$firstName" intro="Tot ce ține de mașinile tale și de comenzile tale, într-un singur loc.">
    <x-slot:actions>
        <form method="post" action="{{ route('customer.logout') }}">
            @csrf
            <button class="st-btn st-btn--ghost st-btn--sm">Ieși din cont</button>
        </form>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        {{-- The car the shop is filtering for, as the largest thing on the page: it is what
             changes what the customer sees everywhere else. --}}
        <section class="overflow-hidden rounded-[3px] bg-white">
            @if($primary)
                <div class="st-tile aspect-[16/8] bg-g2">
                    @if($primary->collection?->garageImageUrl())
                        <img src="{{ $primary->collection->garageImageUrl() }}" alt="" class="st-media">
                    @else
                        <canvas class="st-media" data-st-scene="dusk" data-seed="{{ $primary->id * 5 }}" aria-hidden="true"></canvas>
                    @endif
                    <span class="st-shade"></span>

                    <span class="absolute left-5 top-4 inline-flex items-center gap-1.5 rounded-[2px] bg-fit px-2 py-1 text-[10px] font-semibold uppercase tracking-[.1em] text-white">
                        <x-storefront.icon name="check" class="h-3 w-3" /> Principală
                    </span>

                    <div class="absolute inset-x-5 bottom-4 text-bone">
                        <p class="font-display text-[2.1rem] font-semibold leading-none tracking-[-.02em]">{{ $primary->nickname ?: $primary->make?->name.' '.$primary->model?->name }}</p>
                        <p class="mt-1.5 text-sm text-bone/75">{{ $primary->make?->name }} {{ $primary->model?->name }} · {{ $primary->year }}</p>
                    </div>
                </div>

                <div class="grid gap-4 p-6">
                    @if($vehicle)
                        <p class="text-sm text-ink2">Magazinul filtrează acum pentru <span class="font-semibold text-ink">{{ $vehicle->label() }}</span>.</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('customer.garage.vehicle', $primary->routeSlug()) }}" class="st-btn st-btn--ink st-btn--sm">Detalii și scadențe</a>
                        @if($primary->collection)
                            <a href="{{ $primary->collection->url() }}" class="st-btn st-btn--outline st-btn--sm">Piese pentru ea</a>
                        @endif
                        <a href="{{ route('customer.garage') }}" class="st-btn st-btn--outline st-btn--sm">Garajul meu</a>
                    </div>

                    @if($vehicles->count() > 1)
                        <ul class="grid gap-1 border-t border-line pt-4 text-sm">
                            @foreach($vehicles->where('id', '!=', $primary->id) as $garageVehicle)
                                <li class="flex items-center justify-between gap-3">
                                    <span><span class="font-medium">{{ $garageVehicle->make?->name }} {{ $garageVehicle->model?->name }}</span> <span class="text-ink2">{{ $garageVehicle->year }}</span></span>
                                    <a href="{{ route('customer.garage.vehicle', $garageVehicle->routeSlug()) }}" class="text-ink2 underline underline-offset-2 hover:text-ink">Detalii</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <div class="grid gap-4 p-8">
                    <x-storefront.icon name="car" class="h-9 w-9 text-line2" />
                    <p class="font-display text-2xl font-semibold">Garajul e gol.</p>
                    <p class="text-ink2">Nu ai nicio mașină salvată. <a href="{{ route('customer.garage') }}" class="font-semibold text-ink underline underline-offset-2">Adaugă prima mașină</a> ca să filtrăm automat piesele compatibile.</p>
                </div>
            @endif
        </section>

        <div class="grid content-start gap-3">
            @foreach([
                [route('customer.orders'), $orderCount, $orderCount === 1 ? 'comandă' : 'comenzi', 'box'],
                [route('customer.appointments'), $appointments->count(), $appointments->count() === 1 ? 'programare recentă' : 'programări recente', 'calendar'],
                [route('customer.favourites'), $favouriteCount, $favouriteCount === 1 ? 'produs favorit' : 'produse favorite', 'heart'],
            ] as [$href, $number, $label, $icon])
                <a href="{{ $href }}" class="group flex items-center justify-between gap-4 rounded-[3px] bg-white p-5 transition hover:bg-ink hover:text-light">
                    <span class="flex items-center gap-4">
                        <x-storefront.icon :name="$icon" class="h-6 w-6 text-ink2 group-hover:text-light" />
                        <span>
                            <span class="block font-display text-3xl font-semibold leading-none tabular-nums">{{ $number }}</span>
                            <span class="text-sm text-ink2 group-hover:text-light/70">{{ $label }}</span>
                        </span>
                    </span>
                    <x-storefront.icon name="arrow-right" class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1" />
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-10 grid gap-10 lg:grid-cols-2">
        <section>
            <div class="mb-4 flex items-end justify-between gap-4">
                <h2 class="font-display text-2xl font-semibold">Ultimele comenzi</h2>
                <a href="{{ route('customer.orders') }}" class="st-link">Toate <x-storefront.icon name="arrow-right" /></a>
            </div>

            @forelse($recentOrders as $order)
                <a href="{{ route('storefront.order', $order->checkout_token) }}" class="flex items-center justify-between gap-4 border-t border-line py-4 transition hover:bg-white">
                    <span>
                        <span class="block font-mono text-sm font-medium">{{ $order->number }}</span>
                        <span class="text-sm text-ink2">{{ $order->placed_at?->format('d.m.Y') }} · {{ $order->items->count() }} produse</span>
                    </span>
                    <span class="font-display text-lg font-semibold tabular-nums">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span>
                </a>
            @empty
                <p class="border-t border-line pt-4 text-sm text-ink2">Nu ai încă nicio comandă.</p>
            @endforelse
        </section>

        <section>
            <div class="mb-4 flex items-end justify-between gap-4">
                <h2 class="font-display text-2xl font-semibold">Programări</h2>
                <a href="{{ route('customer.appointments') }}" class="st-link">Toate <x-storefront.icon name="arrow-right" /></a>
            </div>

            @forelse($appointments as $appointment)
                <div class="flex items-center justify-between gap-4 border-t border-line py-4">
                    <span class="min-w-0">
                        <span class="block truncate font-semibold">{{ $appointment->shop?->name }}</span>
                        <span class="text-sm text-ink2">{{ $appointment->service?->name ?? 'Lucrare nespecificată' }} · {{ $appointment->preferred_date?->format('d.m.Y') ?? 'oricând' }}</span>
                    </span>
                    <span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span>
                </div>
            @empty
                <p class="border-t border-line pt-4 text-sm text-ink2">
                    Nicio programare încă. <a href="{{ route('storefront.services') }}" class="font-semibold text-ink underline underline-offset-2">Găsește un service</a>
                </p>
            @endforelse
        </section>
    </div>
</x-storefront.account>
