<div class="space-y-6">
    <h1 class="text-2xl font-black tracking-tight">Comenzile mele</h1>

    @if($orders->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Nu ai încă nicio comandă.
        </p>
    @else
        <div class="space-y-3">
            @foreach($orders as $order)
                <div class="rounded-xl border border-stone-200 bg-white p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <a href="{{ route('storefront.order', $order->checkout_token) }}" class="font-semibold hover:underline">
                            {{ $order->number }}
                        </a>
                        <span class="text-sm text-stone-500">{{ $order->placed_at?->format('d.m.Y') }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-stone-600">
                        <span>{{ $order->items->count() }} produse</span>
                        <span class="font-semibold text-stone-900">{{ number_format((float) $order->grand_total, 2, ',', '.') }} {{ $order->currency }}</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold">{{ $order->status }}</span>
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold">plată: {{ $order->payment_status }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $orders->links() }}</div>
    @endif
</div>
