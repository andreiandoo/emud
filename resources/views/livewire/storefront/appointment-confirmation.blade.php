<div class="mx-auto max-w-2xl space-y-6">
    <x-seo title="Cererea ta de programare" :index="false" />

    <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-5">
        <h1 class="text-xl font-bold tracking-tight text-emerald-900">Cererea a fost trimisă</h1>
        <p class="mt-1 text-sm text-emerald-900">
            {{ $appointment->shop->name }} te contactează la {{ $appointment->customer_phone }} ca să stabiliți ora.
        </p>
        {{-- Repeated here because it is the thing most easily misread: nothing is booked yet. --}}
        <p class="mt-2 text-xs text-emerald-800">
            Este o cerere, nu o rezervare confirmată. Ora se stabilește direct cu service-ul.
        </p>
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-stone-500">Ce ai cerut</h2>

        <dl class="grid gap-y-3 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-stone-500">Service</dt>
            <dd><a href="{{ $appointment->shop->url() }}" class="font-medium underline">{{ $appointment->shop->name }}</a></dd>

            <dt class="text-stone-500">Adresă</dt>
            <dd>{{ collect([$appointment->shop->address, $appointment->shop->city])->filter()->implode(', ') }}</dd>

            <dt class="text-stone-500">Lucrare</dt>
            <dd>{{ $appointment->service?->name ?? 'Nespecificată' }}</dd>

            <dt class="text-stone-500">Mașina</dt>
            <dd>{{ $appointment->vehicleLabel() }}</dd>

            <dt class="text-stone-500">Când</dt>
            <dd>
                {{ $appointment->preferred_date?->format('d.m.Y') ?? 'Oricând' }}
                · {{ $appointment->preferred_slot->label() }}
            </dd>

            @if($appointment->order)
                <dt class="text-stone-500">Comandă</dt>
                <dd>{{ $appointment->order->number }}</dd>
            @endif

            <dt class="text-stone-500">Stare</dt>
            <dd><span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span></dd>
        </dl>

        @if($appointment->message)
            <p class="mt-4 rounded-lg bg-stone-100 p-3 text-sm text-stone-700">{{ $appointment->message }}</p>
        @endif
    </div>

    @auth
        <p class="text-sm text-stone-600">
            Vezi toate cererile în
            <a href="{{ route('customer.appointments') }}" class="font-semibold text-stone-900 underline">programările tale</a>.
        </p>
    @else
        <p class="text-sm text-stone-600">
            Păstrează linkul acestei pagini — este singurul mod de a reveni la cerere fără cont.
        </p>
    @endauth
</div>
