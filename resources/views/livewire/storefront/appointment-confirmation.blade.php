<div>
    <x-seo title="Cererea ta de programare" :index="false" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.06)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>

        <div class="shell grid gap-5 pb-12 pt-14 sm:pt-20">
            <span class="grid h-12 w-12 place-items-center rounded-full bg-fit-bright/15 text-fit-bright">
                <x-storefront.icon name="check" class="h-6 w-6" />
            </span>
            <h1 class="st-display text-[clamp(2.25rem,4.4vw,4.25rem)]">Cererea a fost trimisă</h1>
            <p class="max-w-[60ch] text-[#cfcdc6]">
                {{ $appointment->shop->name }} te contactează la <span class="font-mono text-bone">{{ $appointment->customer_phone }}</span> ca să stabiliți ora.
            </p>
            {{-- Repeated here because it is the thing most easily misread: nothing is booked yet. --}}
            <p class="text-sm text-mute">Este o cerere, nu o rezervare confirmată. Ora se stabilește direct cu service-ul.</p>
        </div>
    </section>

    <div class="shell grid gap-8 pb-24 pt-10 sm:pt-14 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <section class="rounded-[3px] bg-white p-6 sm:p-8">
            <h2 class="st-kicker mb-5 text-ink2">Ce ai cerut</h2>

            <dl class="grid border-t border-line text-sm sm:grid-cols-[10rem_1fr]">
                @foreach([
                    ['Adresă', collect([$appointment->shop->address, $appointment->shop->city])->filter()->implode(', ')],
                    ['Lucrare', $appointment->service?->name ?? 'Nespecificată'],
                    ['Mașina', $appointment->vehicleLabel()],
                    ['Când', ($appointment->preferred_date?->format('d/m/Y') ?? 'Oricând').' · '.$appointment->preferred_slot->label()],
                ] as [$label, $value])
                    <dt class="border-b border-line py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2 sm:py-3.5">{{ $label }}</dt>
                    <dd class="border-b border-line pb-3 font-medium sm:py-3.5">{{ $value }}</dd>
                @endforeach

                <dt class="border-b border-line py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2 sm:py-3.5">Service</dt>
                <dd class="border-b border-line pb-3 sm:py-3.5"><a href="{{ $appointment->shop->url() }}" class="font-medium underline underline-offset-2">{{ $appointment->shop->name }}</a></dd>

                @if($appointment->order)
                    <dt class="border-b border-line py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2 sm:py-3.5">Comandă</dt>
                    <dd class="border-b border-line pb-3 font-mono sm:py-3.5">{{ $appointment->order->number }}</dd>
                @endif

                <dt class="py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2 sm:py-3.5">Stare</dt>
                <dd class="pb-3 sm:py-3.5"><span class="{{ $appointment->status->pillClass() }}">{{ $appointment->status->label() }}</span></dd>
            </dl>

            @if($appointment->message)
                <p class="mt-5 rounded-[3px] bg-light p-4 text-sm text-ink2">{{ $appointment->message }}</p>
            @endif
        </section>

        <aside class="grid gap-4 rounded-[3px] bg-sand p-6 text-ink">
            @auth
                <p class="text-sm text-sandink">Vezi toate cererile în programările tale.</p>
                <a href="{{ route('customer.appointments') }}" class="st-btn st-btn--ink">Programările mele</a>
            @else
                <p class="text-sm text-sandink">
                    Păstrează linkul acestei pagini — este singurul mod de a reveni la cerere fără cont.
                </p>
                <a href="{{ route('customer.register') }}" class="st-btn st-btn--outline">Creează un cont</a>
            @endauth
            <a href="{{ route('storefront.home') }}" class="st-link w-fit"><x-storefront.icon name="arrow-left" /> Înapoi în magazin</a>
        </aside>
    </div>
</div>
