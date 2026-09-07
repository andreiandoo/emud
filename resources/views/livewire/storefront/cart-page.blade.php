<div class="space-y-6">
    <h1 class="text-2xl font-black tracking-tight">Coșul meu</h1>

    @if($items->isEmpty())
        <p class="rounded-xl border border-dashed border-stone-300 p-8 text-center text-sm text-stone-500">
            Coșul este gol.
            <a href="{{ route('storefront.home') }}" class="font-semibold underline">Începe cu mașina ta</a>.
        </p>
    @else
        <div class="space-y-3">
            @foreach($items as $item)
                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-stone-200 bg-white p-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('storefront.product', $item->product) }}" class="font-semibold hover:underline">
                            {{ $item->snapshot['name'] ?? $item->product->name }}
                        </a>
                        <div class="mt-0.5 text-xs text-stone-500">
                            @if($item->snapshot['sku'] ?? null) Cod: {{ $item->snapshot['sku'] }} @endif
                            @if($item->variant?->name) · {{ $item->variant->name }} @endif
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <span class="text-stone-600">Cant.</span>
                        <input type="number" min="0" value="{{ $item->quantity }}"
                               wire:change="setQuantity({{ $item->id }}, $event.target.value)"
                               class="w-20 rounded-lg border-stone-300 text-sm">
                    </label>

                    <div class="w-28 text-right font-semibold">
                        {{ number_format((float) $item->unit_price * $item->quantity, 2, ',', '.') }} {{ $currency }}
                    </div>

                    <button wire:click="remove({{ $item->id }})" class="text-sm text-red-600 underline hover:text-red-800">Șterge</button>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-stone-200 bg-white p-5">
            <div>
                <span class="block text-sm text-stone-500">Subtotal</span>
                <span class="text-2xl font-black">{{ number_format($subtotal, 2, ',', '.') }} {{ $currency }}</span>
                <span class="mt-1 block text-xs text-stone-500">Transportul se calculează la finalizare.</span>
            </div>

            <a href="{{ route('storefront.checkout') }}" class="rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-stone-700">
                Finalizează comanda
            </a>
        </div>
    @endif
</div>
