<div class="mx-auto max-w-2xl space-y-6">
    <x-seo title="Comanda ta" :index="false" :follow="false" />

    <div class="rounded-xl border border-lime-300 bg-lime-50 p-6">
        <h1 class="text-2xl font-black tracking-tight">Comanda a fost înregistrată</h1>
        <p class="mt-1 text-sm text-stone-700">
            Numărul comenzii este <span class="font-semibold">{{ $order->number }}</span>.
            Ți-am trimis detaliile pe {{ $order->customer_email }}.
        </p>
    </div>

    {{-- The order is recorded, but a payment is only complete once the provider confirms it,
         so this page never claims the money has been taken. --}}
    <div class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-2 text-lg font-bold">Plata</h2>
        @if($transaction?->redirect_url)
            <p class="text-sm text-stone-600">Finalizează plata la procesator pentru a confirma comanda.</p>
            <a href="{{ $transaction->redirect_url }}" class="mt-3 inline-block rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white">
                Continuă către plată
            </a>
        @else
            <p class="text-sm text-stone-600">
                Starea plății: <span class="font-semibold">{{ $transaction?->status ?? 'în așteptare' }}</span>.
                Confirmarea vine de la procesator; îți scriem imediat ce o primim.
            </p>
        @endif
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-6">
        <h2 class="mb-3 text-lg font-bold">Produse</h2>
        <ul class="space-y-2 text-sm">
            @foreach($order->items as $item)
                <li class="flex justify-between gap-3">
                    <span class="min-w-0 flex-1">{{ $item->name }} × {{ $item->quantity }}</span>
                    <span class="font-medium">{{ \App\Support\Money::of($item->line_total, $order->currency)->format() }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-3 space-y-1 border-t border-stone-100 pt-3 text-sm">
            <div class="flex justify-between"><span class="text-stone-600">Subtotal</span><span>{{ \App\Support\Money::of($order->subtotal, $order->currency)->format() }}</span></div>
            <div class="flex justify-between"><span class="text-stone-600">Transport</span><span>{{ \App\Support\Money::of($order->shipping_total, $order->currency)->format() }}</span></div>
            <div class="flex justify-between pt-2 text-lg font-black"><span>Total</span><span>{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span></div>
        </div>
    </div>

    @if($fitters->isNotEmpty())
        {{-- The one moment the customer is certainly thinking about who will fit the part.
             The order token travels with the link so the request arrives at the workshop with
             the parts list attached instead of "ceva de la eMUD". --}}
        <section class="space-y-3 rounded-xl border border-stone-900 bg-white p-6">
            <h2 class="text-lg font-bold tracking-tight">Ai nevoie de montaj?</h2>
            <p class="text-sm text-stone-600">
                Service-uri din {{ $order->shippingAddress?->city }} care montează piese cumpărate de la noi.
            </p>

            <div class="space-y-2">
                @foreach($fitters as $fitter)
                    <a href="{{ $fitter->url() }}?order={{ $order->checkout_token }}#programare"
                       class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-stone-200 p-4 transition hover:border-stone-900">
                        <span class="min-w-0">
                            <span class="block font-semibold text-stone-900">{{ $fitter->name }}</span>
                            <span class="block text-sm text-stone-500">{{ $fitter->address ?: $fitter->city }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-stone-900 underline underline-offset-4">Cere o programare</span>
                    </a>
                @endforeach
            </div>

            <a href="{{ route('storefront.services', ['city' => $order->shippingAddress?->city, 'fitsOurParts' => 1]) }}"
               class="inline-block text-sm font-semibold underline underline-offset-4">Vezi toate service-urile din oraș</a>
        </section>
    @endif

    <a href="{{ route('storefront.home') }}" class="inline-block text-sm font-semibold underline">Înapoi în magazin</a>
</div>
