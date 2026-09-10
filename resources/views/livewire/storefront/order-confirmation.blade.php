<div>
    <x-seo title="Comanda ta" :index="false" :follow="false" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.05)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        <div class="shell grid gap-5 pb-12 pt-12 sm:pt-16">
            <p class="st-kicker text-mute">Comandă plasată</p>
            <h1 class="st-display text-[clamp(2.25rem,4.4vw,4.25rem)]">Comanda a fost înregistrată</h1>
            <p class="max-w-[60ch] text-[#cfcdc6]">
                Numărul comenzii este <span class="font-mono font-semibold text-bone">{{ $order->number }}</span>.
                Ți-am trimis detaliile pe {{ $order->customer_email }}.
            </p>
        </div>
    </section>

    <div class="shell grid gap-10 pb-24 pt-10 sm:pt-14 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
        <div class="grid content-start gap-6">
            {{-- The order is recorded, but a payment is only complete once the provider confirms
                 it, so this page never claims the money has been taken. --}}
            <section class="grid gap-4 rounded-[3px] bg-white p-6 sm:p-8">
                <h2 class="font-display text-2xl font-semibold">Plata</h2>

                @if($transaction?->redirect_url)
                    <p class="text-ink2">Finalizează plata la procesator pentru a confirma comanda.</p>
                    <div>
                        <a href="{{ $transaction->redirect_url }}" class="st-btn">Continuă către plată <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
                    </div>
                @else
                    <p class="text-ink2">
                        Starea plății: <span class="font-semibold text-ink">{{ $transaction?->status ?? 'în așteptare' }}</span>.
                        Confirmarea vine de la procesator; îți scriem imediat ce o primim.
                    </p>
                @endif
            </section>

            @if($fitters->isNotEmpty())
                {{-- The one moment the customer is certainly thinking about who will fit the part.
                     The order token travels with the link so the request arrives at the workshop
                     with the parts list attached instead of "ceva de la eMUD". --}}
                <section class="grid gap-5 rounded-[3px] bg-sand p-6 text-ink sm:p-8">
                    <div class="grid gap-2">
                        <p class="st-kicker text-sandink">Montaj</p>
                        <h2 class="font-display text-2xl font-semibold">Ai nevoie de montaj?</h2>
                        <p class="text-sandink">Service-uri din {{ $order->shippingAddress?->city }} care montează piese cumpărate de la noi.</p>
                    </div>

                    <div class="grid gap-2">
                        @foreach($fitters as $fitter)
                            <a href="{{ $fitter->url() }}?order={{ $order->checkout_token }}#programare"
                               class="group flex flex-wrap items-center justify-between gap-3 rounded-[3px] bg-[#eee7d9] p-4 transition hover:bg-ink hover:text-light">
                                <span class="min-w-0">
                                    <span class="block font-semibold">{{ $fitter->name }}</span>
                                    <span class="block text-sm opacity-75">{{ $fitter->address ?: $fitter->city }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2 text-sm font-semibold">Cere o programare <x-storefront.icon name="arrow-right" class="h-4 w-4 transition-transform duration-500 group-hover:translate-x-1" /></span>
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ route('storefront.services', ['city' => $order->shippingAddress?->city, 'fitsOurParts' => 1]) }}"
                       class="st-link w-fit">Vezi toate service-urile din oraș <x-storefront.icon name="arrow-right" /></a>
                </section>
            @endif

            <a href="{{ route('storefront.home') }}" class="st-link w-fit"><x-storefront.icon name="arrow-left" /> Înapoi în magazin</a>
        </div>

        <aside class="grid gap-5 rounded-[3px] bg-white p-6">
            <h2 class="font-display text-xl font-semibold">Produse</h2>

            <ul class="grid gap-3 border-t border-line pt-4 text-sm">
                @foreach($order->items as $item)
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0 flex-1">{{ $item->name }} <span class="text-ink2">× {{ $item->quantity }}</span></span>
                        <span class="font-medium tabular-nums">{{ \App\Support\Money::of($item->line_total, $order->currency)->format() }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="grid gap-2 border-t border-line pt-4 text-sm">
                <div class="flex justify-between"><span class="text-ink2">Subtotal</span><span class="tabular-nums">{{ \App\Support\Money::of($order->subtotal, $order->currency)->format() }}</span></div>
                <div class="flex justify-between"><span class="text-ink2">Transport</span><span class="tabular-nums">{{ \App\Support\Money::of($order->shipping_total, $order->currency)->format() }}</span></div>
            </div>

            <div class="flex items-baseline justify-between border-t border-line pt-4">
                <span class="font-semibold">Total</span>
                <span class="font-display text-3xl font-semibold tabular-nums">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span>
            </div>
        </aside>
    </div>
</div>
