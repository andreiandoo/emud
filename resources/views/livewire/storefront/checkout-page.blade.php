<div>
    <x-seo title="Finalizare comandă" :index="false" :follow="false" />

    <section class="relative isolate overflow-hidden bg-g0 text-bone">
        <canvas data-st-topo="rgba(241,238,230,.05)" class="pointer-events-none absolute inset-0 -z-10 h-full w-full" aria-hidden="true"></canvas>
        <div class="shell flex flex-wrap items-end justify-between gap-6 pb-12 pt-12 sm:pt-16">
            <div class="grid gap-4">
                <p class="st-kicker text-mute">Comandă</p>
                <h1 class="st-display text-[clamp(2.25rem,4.4vw,4.25rem)]">Finalizare comandă</h1>
            </div>

            <ol class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[.1em] text-mute">
                <li class="flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full border border-gl2 text-bone"><x-storefront.icon name="check" class="h-3.5 w-3.5" /></span> Coș</li>
                <li class="h-px w-6 bg-gl2" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-bone"><span class="grid h-6 w-6 place-items-center rounded-full bg-signal text-ink">2</span> Livrare</li>
                <li class="h-px w-6 bg-gl2" aria-hidden="true"></li>
                <li class="flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-full border border-gl2">3</span> Confirmare</li>
            </ol>
        </div>
    </section>

    <div class="shell pb-24 pt-10 sm:pt-14">
        @if($failure)
            <p class="mb-8 rounded-[3px] border border-red-300 bg-red-50 p-4 text-sm text-red-800">{{ $failure }}</p>
        @endif

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
            <form wire:submit="place" class="grid gap-6">
                <section class="grid gap-5 rounded-[3px] bg-white p-6 sm:p-8">
                    <h2 class="flex items-center gap-3 font-display text-xl font-semibold">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-ink font-mono text-xs text-light">1</span> Date de contact
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="field-label">Email</span>
                            <input type="email" wire:model="email" autocomplete="email">
                            @error('email') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="field-label">Telefon</span>
                            <input type="tel" wire:model="phone" autocomplete="tel">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </section>

                <section class="grid gap-5 rounded-[3px] bg-white p-6 sm:p-8">
                    <h2 class="flex items-center gap-3 font-display text-xl font-semibold">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-ink font-mono text-xs text-light">2</span> Date de livrare
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="field-label">Nume</span>
                            <input type="text" wire:model="last_name" autocomplete="family-name">
                            @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="field-label">Prenume</span>
                            <input type="text" wire:model="first_name" autocomplete="given-name">
                            @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <label class="block">
                        <span class="field-label">Adresă</span>
                        <input type="text" wire:model="line_1" autocomplete="street-address">
                        @error('line_1') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="field-label">Localitate</span>
                            <input type="text" wire:model="city" autocomplete="address-level2">
                            @error('city') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="field-label">Județ</span>
                            <input type="text" wire:model="county" autocomplete="address-level1">
                        </label>
                        <label class="block">
                            <span class="field-label">Cod poștal</span>
                            <input type="text" wire:model="postal_code" autocomplete="postal-code" inputmode="numeric">
                        </label>
                    </div>
                </section>

                <section class="grid gap-5 rounded-[3px] bg-white p-6 sm:p-8">
                    <h2 class="flex items-center gap-3 font-display text-xl font-semibold">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-ink font-mono text-xs text-light">3</span> Livrare
                    </h2>

                    @if($methods->isEmpty())
                        <p class="text-sm text-ink2">Nu există metode de livrare active. Configurează-le din administrare.</p>
                    @else
                        <div class="grid gap-2">
                            @foreach($methods as $shippingMethod)
                                <label class="flex cursor-pointer items-center gap-4 rounded-[3px] border border-line p-4 transition hover:border-line2 has-[:checked]:border-ink has-[:checked]:bg-light">
                                    <input type="radio" wire:model.live="shippingMethodId" value="{{ $shippingMethod->id }}">
                                    <x-storefront.icon name="truck" class="h-5 w-5 text-ink2" />
                                    <span class="flex-1 font-medium">{{ $shippingMethod->name }}</span>
                                    <span class="font-display text-lg font-semibold tabular-nums">{{ $shippingMethod->priceFor($subtotal)->format() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('shippingMethodId') <span class="field-error">{{ $message }}</span> @enderror
                    @endif

                    <label class="block">
                        <span class="field-label">Observații <span class="font-normal opacity-70">(opțional)</span></span>
                        <textarea wire:model="note" rows="3" placeholder="Ex.: sunați înainte de livrare"></textarea>
                    </label>
                </section>

                <div class="grid gap-3">
                    <button type="submit" @disabled($methods->isEmpty() || $providers->isEmpty()) class="st-btn st-btn--block">
                        <span wire:loading.remove wire:target="place">Trimite comanda</span>
                        <span wire:loading wire:target="place">Se trimite comanda…</span>
                    </button>

                    @if($providers->isEmpty())
                        <p class="text-xs text-ink2">Niciun procesator de plăți nu este activ, așa că plasarea comenzii este dezactivată.</p>
                    @endif
                </div>
            </form>

            <aside class="grid gap-5 rounded-[3px] bg-white p-6 lg:sticky lg:top-[calc(var(--st-header-h)+1.5rem)]">
                <h2 class="font-display text-xl font-semibold">Sumar</h2>

                <ul class="grid gap-3 border-t border-line pt-4 text-sm">
                    @foreach($items as $item)
                        <li class="flex justify-between gap-3">
                            <span class="min-w-0 flex-1">{{ $item->snapshot['name'] ?? $item->product->name }} <span class="text-ink2">× {{ $item->quantity }}</span></span>
                            <span class="font-medium tabular-nums">{{ $lineTotal($item)->format() }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="grid gap-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-ink2">Subtotal</span><span class="tabular-nums">{{ $subtotal->format() }}</span></div>
                    <div class="flex justify-between"><span class="text-ink2">Transport</span><span class="tabular-nums">{{ $shippingTotal->format() }}</span></div>
                </div>

                <div class="flex items-baseline justify-between border-t border-line pt-4">
                    <span class="font-semibold">Total</span>
                    <span class="font-display text-3xl font-semibold tabular-nums">{{ $subtotal->plus($shippingTotal)->format() }}</span>
                </div>

                <a href="{{ route('storefront.cart') }}" class="text-sm text-ink2 underline underline-offset-2 hover:text-ink">Modifică produsele din coș</a>
            </aside>
        </div>
    </div>
</div>
