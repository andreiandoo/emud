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
                    <span class="font-medium">{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $order->currency }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-3 space-y-1 border-t border-stone-100 pt-3 text-sm">
            <div class="flex justify-between"><span class="text-stone-600">Subtotal</span><span>{{ number_format((float) $order->subtotal, 2, ',', '.') }}</span></div>
            <div class="flex justify-between"><span class="text-stone-600">Transport</span><span>{{ number_format((float) $order->shipping_total, 2, ',', '.') }}</span></div>
            <div class="flex justify-between pt-2 text-lg font-black"><span>Total</span><span>{{ number_format((float) $order->grand_total, 2, ',', '.') }} {{ $order->currency }}</span></div>
        </div>
    </div>

    <a href="{{ route('storefront.home') }}" class="inline-block text-sm font-semibold underline">Înapoi în magazin</a>
</div>
