<div class="space-y-6">
    <x-seo title="Finalizare comandă" :index="false" :follow="false" />

    <h1 class="text-2xl font-black tracking-tight">Finalizare comandă</h1>

    @if($failure)
        <p class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ $failure }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <form wire:submit="place" class="space-y-5 rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Date de livrare</h2>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Nume</span>
                    <input type="text" wire:model="last_name" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('last_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Prenume</span>
                    <input type="text" wire:model="first_name" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('first_name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Email</span>
                    <input type="email" wire:model="email" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('email') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Telefon</span>
                    <input type="tel" wire:model="phone" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('phone') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Adresă</span>
                <input type="text" wire:model="line_1" class="w-full rounded-lg border-stone-300 text-sm">
                @error('line_1') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Localitate</span>
                    <input type="text" wire:model="city" class="w-full rounded-lg border-stone-300 text-sm">
                    @error('city') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Județ</span>
                    <input type="text" wire:model="county" class="w-full rounded-lg border-stone-300 text-sm">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-stone-600">Cod poștal</span>
                    <input type="text" wire:model="postal_code" class="w-full rounded-lg border-stone-300 text-sm">
                </label>
            </div>

            <h2 class="pt-2 text-lg font-bold">Livrare</h2>
            @if($methods->isEmpty())
                <p class="text-sm text-stone-500">Nu există metode de livrare active. Configurează-le din administrare.</p>
            @else
                <div class="space-y-2">
                    @foreach($methods as $shippingMethod)
                        <label class="flex items-center gap-3 rounded-lg border border-stone-200 p-3 text-sm">
                            <input type="radio" wire:model.live="shippingMethodId" value="{{ $shippingMethod->id }}">
                            <span class="flex-1">{{ $shippingMethod->name }}</span>
                            <span class="font-semibold">{{ $shippingMethod->priceFor($subtotal)->format() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('shippingMethodId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            @endif

            <label class="block">
                <span class="mb-1 block text-xs font-medium text-stone-600">Observații <span class="font-normal text-stone-400">(opțional)</span></span>
                <textarea wire:model="note" rows="3" class="w-full rounded-lg border-stone-300 text-sm"></textarea>
            </label>

            <button type="submit" @disabled($methods->isEmpty() || $providers->isEmpty())
                    class="w-full rounded-lg bg-stone-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-stone-700 disabled:bg-stone-300">
                Trimite comanda
            </button>

            @if($providers->isEmpty())
                <p class="text-xs text-stone-500">Niciun procesator de plăți nu este activ, așa că plasarea comenzii este dezactivată.</p>
            @endif
        </form>

        <aside class="h-fit space-y-3 rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Sumar</h2>
            <ul class="space-y-2 text-sm">
                @foreach($items as $item)
                    <li class="flex justify-between gap-3">
                        <span class="min-w-0 flex-1">{{ $item->snapshot['name'] ?? $item->product->name }} × {{ $item->quantity }}</span>
                        <span class="font-medium">{{ $lineTotal($item)->format() }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="space-y-1 border-t border-stone-100 pt-3 text-sm">
                <div class="flex justify-between"><span class="text-stone-600">Subtotal</span><span>{{ $subtotal->format() }}</span></div>
                <div class="flex justify-between"><span class="text-stone-600">Transport</span><span>{{ $shippingTotal->format() }}</span></div>
                <div class="flex justify-between pt-2 text-lg font-black"><span>Total</span><span>{{ $subtotal->plus($shippingTotal)->format() }}</span></div>
            </div>
        </aside>
    </div>
</div>
