<x-storefront.account active="orders" title="Comenzile mele" intro="Fiecare comandă, cu starea ei și a plății. Deschide una ca să vezi produsele și să ceri montajul.">
    @if($orders->isEmpty())
        <div class="grid place-items-center gap-4 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-16 text-center">
            <x-storefront.icon name="box" class="h-10 w-10 text-line2" />
            <p class="font-display text-2xl font-semibold">Nu ai încă nicio comandă.</p>
            <a href="{{ route('storefront.home') }}" class="st-btn st-btn--ink">Înapoi în magazin</a>
        </div>
    @else
        <div class="overflow-hidden rounded-[3px] border border-line bg-white">
            <div class="hidden grid-cols-[1.2fr_1fr_.8fr_1fr_1.2fr] gap-4 border-b border-line bg-light px-5 py-3 font-mono text-[11px] uppercase tracking-[.1em] text-ink2 md:grid">
                <span>Comanda</span><span>Data</span><span>Produse</span><span>Total</span><span>Stare</span>
            </div>

            @foreach($orders as $order)
                <a href="{{ route('storefront.order', $order->checkout_token) }}"
                   class="grid gap-2 border-b border-line px-5 py-4 transition last:border-b-0 hover:bg-light md:grid-cols-[1.2fr_1fr_.8fr_1fr_1.2fr] md:items-center md:gap-4">
                    <span class="font-mono text-sm font-medium">{{ $order->number }}</span>
                    <span class="text-sm text-ink2">{{ $order->placed_at?->format('d.m.Y') }}</span>
                    <span class="text-sm text-ink2">{{ $order->items->count() }} produse</span>
                    <span class="font-display text-lg font-semibold tabular-nums">{{ \App\Support\Money::of($order->grand_total, $order->currency)->format() }}</span>
                    <span class="flex flex-wrap gap-1.5">
                        <span class="pill-neutral">{{ $order->status }}</span>
                        <span class="pill-neutral">plată: {{ $order->payment_status }}</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-storefront.account>
