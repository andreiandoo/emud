<div x-data="{ open: false }"
     @open-cart.window="open = true"
     @cart-added.window="open = true"
     @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="Coșul tău">
        <div x-show="open" x-transition.opacity.duration.300ms @click="open = false" class="absolute inset-0 bg-g0/60 backdrop-blur-sm"></div>

        <aside x-show="open" x-trap.noscroll="open" data-lenis-prevent
               x-transition:enter="transition duration-500 ease-[cubic-bezier(.2,.8,.2,1)]"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition duration-300 ease-in"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full"
               class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-light text-ink shadow-2xl">
            <header class="flex items-center justify-between gap-4 border-b border-line px-6 py-5">
                <div>
                    <p class="st-kicker text-ink2">Coșul tău</p>
                    <p class="mt-1.5 font-display text-2xl font-semibold tracking-tight">
                        {{ $count }} {{ $count === 1 ? 'produs' : 'produse' }}
                    </p>
                </div>

                <button type="button" @click="open = false" class="grid h-11 w-11 place-items-center rounded-full border border-line2 transition hover:border-ink" aria-label="Închide coșul">
                    <x-storefront.icon name="close" class="h-5 w-5" />
                </button>
            </header>

            @if($items->isEmpty())
                <div class="grid flex-1 place-content-center gap-4 p-8 text-center">
                    <x-storefront.icon name="cart" class="mx-auto h-10 w-10 text-line2" />
                    <p class="font-display text-xl font-semibold">Coșul este gol.</p>
                    <p class="text-sm text-ink2">Alege-ți mașina și îți arătăm ce i se potrivește.</p>
                    <button type="button" @click="open = false; $dispatch('open-vehicle-selector', { tab: 'car' })" class="st-btn st-btn--ink mx-auto">
                        Alege mașina
                    </button>
                </div>
            @else
                <ul class="flex-1 divide-y divide-line overflow-y-auto overscroll-contain px-6">
                    @foreach($items as $item)
                        @php($image = $item->product?->media->first())
                        <li class="grid grid-cols-[4.5rem_1fr] gap-4 py-5" wire:key="drawer-line-{{ $item->id }}">
                            <a href="{{ $item->product ? route('storefront.product', $item->product) : '#' }}"
                               class="grid aspect-square place-items-center overflow-hidden rounded-[3px] border border-line bg-white">
                                @if($image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}" alt="" class="h-full w-full object-contain p-1.5 mix-blend-multiply">
                                @else
                                    <x-storefront.icon name="part" class="h-7 w-7 text-line2" />
                                @endif
                            </a>

                            <div class="grid min-w-0 gap-2">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="min-w-0 text-[15px] font-semibold leading-snug">{{ $item->snapshot['name'] ?? $item->product?->name }}</p>
                                    <button type="button" wire:click="remove({{ $item->id }})" class="shrink-0 text-ink2 transition hover:text-ink" aria-label="Scoate din coș">
                                        <x-storefront.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>

                                @if($item->snapshot['sku'] ?? null)
                                    <p class="font-mono text-[11px] uppercase tracking-[.08em] text-ink2">Cod {{ $item->snapshot['sku'] }}</p>
                                @endif

                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex h-9 items-center rounded-[3px] border border-line2">
                                        <button type="button" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="grid h-full w-9 place-items-center transition hover:bg-white" aria-label="Scade cantitatea">
                                            <x-storefront.icon name="minus" class="h-3.5 w-3.5" />
                                        </button>
                                        <span class="w-8 text-center font-mono text-sm tabular-nums">{{ $item->quantity }}</span>
                                        <button type="button" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="grid h-full w-9 place-items-center transition hover:bg-white" aria-label="Crește cantitatea">
                                            <x-storefront.icon name="plus" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>

                                    <span class="font-display text-lg font-semibold tabular-nums">{{ $lineTotal($item)->format() }}</span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <footer class="grid gap-4 border-t border-line bg-white px-6 py-5">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-sm text-ink2">Subtotal</span>
                        <span class="font-display text-2xl font-semibold tabular-nums">{{ $subtotal->format() }}</span>
                    </div>
                    <p class="-mt-2 text-xs text-ink2">Transportul se calculează la finalizare.</p>

                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('storefront.cart') }}" class="st-btn st-btn--outline">Vezi coșul</a>
                        <a href="{{ route('storefront.checkout') }}" class="st-btn">Finalizează</a>
                    </div>
                </footer>
            @endif
        </aside>
    </div>
</div>
