<div>
    <x-seo title="Coșul meu" :index="false" :follow="false" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.05)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        <div class="shell flex flex-wrap items-end justify-between gap-6 pb-12 pt-12 sm:pt-16">
            <div class="grid gap-4">
                <p class="st-kicker text-mute">Coș</p>
                <h1 class="st-display text-[clamp(2.25rem,4.4vw,4.25rem)]">Coșul meu</h1>
            </div>

            {{-- The three steps, so the customer knows how far checkout is from here. --}}
            <ol class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[.1em] text-mute">
                <li class="flex items-center gap-2 text-bone"><span class="grid h-6 w-6 place-items-center rounded-full bg-signal text-ink">1</span> Coș</li>
                <li class="h-px w-6 bg-gl2" aria-hidden="true"></li>
                <li class="flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full border border-gl2">2</span> Livrare</li>
                <li class="h-px w-6 bg-gl2" aria-hidden="true"></li>
                <li class="flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full border border-gl2">3</span> Confirmare</li>
            </ol>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-14">
        @if($items->isEmpty())
            <div class="grid place-items-center gap-5 rounded-[3px] border border-dashed border-line2 bg-white px-6 py-20 text-center">
                <x-storefront.icon name="cart" class="h-10 w-10 text-line2" />
                <p class="font-display text-2xl font-semibold">Coșul este gol.</p>
                <p class="max-w-md text-ink2">Spune-ne ce mașină ai și îți arătăm doar piesele care i se potrivesc.</p>
                <a href="{{ route('storefront.home') }}" class="st-btn st-btn--ink">Începe cu mașina ta <x-storefront.icon name="arrow-right" class="st-arrow" /></a>
            </div>
        @else
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
                <ul class="divide-y divide-line border-y border-line">
                    @foreach($items as $item)
                        @php($image = $item->product?->media->first())
                        <li class="grid grid-cols-[5.5rem_1fr] gap-5 py-6 sm:grid-cols-[7.5rem_1fr]" wire:key="cart-line-{{ $item->id }}">
                            <a href="{{ $item->product ? route('storefront.product', $item->product) : '#' }}"
                               class="grid aspect-square place-items-center overflow-hidden rounded-[3px] border border-line bg-[radial-gradient(70%_62%_at_50%_40%,#ffffff_0%,#f2efe8_62%,#e6e0d3_100%)]">
                                @if($image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk($image->disk)->url($image->path) }}" alt="" class="h-full w-full object-contain p-2 mix-blend-multiply">
                                @else
                                    <x-storefront.icon name="part" class="h-9 w-9 text-line2" />
                                @endif
                            </a>

                            <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-6">
                                <div class="min-w-0">
                                    @if($item->product?->brand)
                                        <p class="font-mono text-[11px] uppercase tracking-[.1em] text-ink2">{{ $item->product->brand->name }}</p>
                                    @endif
                                    <a href="{{ $item->product ? route('storefront.product', $item->product) : '#' }}" class="mt-1 block text-[16px] font-semibold leading-snug hover:underline">
                                        {{ $item->snapshot['name'] ?? $item->product?->name }}
                                    </a>
                                    <p class="mt-1 text-xs text-ink2">
                                        @if($item->snapshot['sku'] ?? null) Cod: {{ $item->snapshot['sku'] }} @endif
                                        @if($item->variant?->name) · {{ $item->variant->name }} @endif
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 items-center rounded-[3px] border border-line2 bg-white">
                                        <button type="button" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="grid h-full w-10 place-items-center transition hover:bg-light" aria-label="Scade cantitatea">
                                            <x-storefront.icon name="minus" class="h-4 w-4" />
                                        </button>
                                        <label class="sr-only" for="qty-{{ $item->id }}">Cantitate</label>
                                        <input id="qty-{{ $item->id }}" type="number" min="0" value="{{ $item->quantity }}"
                                               wire:change="setQuantity({{ $item->id }}, $event.target.value)"
                                               class="h-full w-12 rounded-none border-0 bg-transparent p-0 text-center font-mono tabular-nums focus:ring-0">
                                        <button type="button" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="grid h-full w-10 place-items-center transition hover:bg-light" aria-label="Crește cantitatea">
                                            <x-storefront.icon name="plus" class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <button wire:click="remove({{ $item->id }})" class="grid h-11 w-11 place-items-center rounded-[3px] text-ink2 transition hover:bg-white hover:text-ink" aria-label="Șterge">
                                        <x-storefront.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>

                                <p class="font-display text-xl font-semibold tabular-nums sm:w-32 sm:text-right">{{ $lineTotal($item)->format() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="grid gap-5 rounded-[3px] bg-white p-6 lg:sticky lg:top-[calc(var(--st-header-h)+1.5rem)]">
                    <h2 class="font-display text-xl font-semibold">Sumar</h2>

                    <div class="grid gap-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between"><span class="text-ink2">Subtotal</span><span class="font-semibold tabular-nums">{{ $subtotal->format() }}</span></div>
                        <div class="flex justify-between"><span class="text-ink2">Transport</span><span class="text-ink2">la finalizare</span></div>
                    </div>

                    <div class="flex items-baseline justify-between border-t border-line pt-4">
                        <span class="font-semibold">Total estimat</span>
                        <span class="font-display text-3xl font-semibold tabular-nums">{{ $subtotal->format() }}</span>
                    </div>
                    <p class="-mt-3 text-xs text-ink2">Transportul se calculează la finalizare.</p>

                    <a href="{{ route('storefront.checkout') }}" class="st-btn st-btn--block">
                        Finalizează comanda <x-storefront.icon name="arrow-right" class="st-arrow" />
                    </a>

                    <ul class="grid gap-2.5 border-t border-line pt-4 text-[13px] text-ink2">
                        <li class="flex items-center gap-2.5"><x-storefront.icon name="shield" class="h-4 w-4 text-ink" /> Compatibilitate verificată pe mașina ta</li>
                        <li class="flex items-center gap-2.5"><x-storefront.icon name="return" class="h-4 w-4 text-ink" /> Retur în 14 zile</li>
                        <li class="flex items-center gap-2.5"><x-storefront.icon name="wrench" class="h-4 w-4 text-ink" /> Montaj la un service partener</li>
                    </ul>
                </aside>
            </div>
        @endif
    </div>
</div>
