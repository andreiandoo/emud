<div class="space-y-6">
    @if(session('success'))
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <p class="text-sm text-stone-600">
        Alegi procesatorul activ, mediul și curierul de aici. Secretele sunt criptate în baza de date
        și nu se afișează înapoi — un câmp lăsat gol păstrează valoarea existentă.
    </p>

    <form wire:submit="save" class="space-y-6">
        <section class="card-padded">
            <h2 class="mb-4 text-base font-semibold">Procesatoare de plăți</h2>

            <div class="space-y-4">
                @foreach($payments as $index => $provider)
                    <div class="rounded-lg border border-stone-200 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="font-medium">{{ $provider['name'] }}</span>
                                <span class="ml-2 {{ $provider['configured'] ? 'pill-positive' : 'pill-neutral' }}">
                                    {{ $provider['configured'] ? 'configurat' : 'neconfigurat' }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="payments.{{ $index }}.is_active">
                                    Activ
                                </label>

                                <button type="button" wire:click="setDefaultPayment({{ $index }})"
                                        class="{{ $provider['is_default'] ? 'pill-positive' : 'pill-neutral' }}">
                                    {{ $provider['is_default'] ? 'Implicit' : 'Setează implicit' }}
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="block">
                                <span class="field-label">Mediu</span>
                                <select wire:model="payments.{{ $index }}.mode">
                                    <option value="sandbox">Sandbox</option>
                                    <option value="live">Live</option>
                                </select>
                                @error('payments.'.$index.'.mode') <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            @if($provider['code'] === 'stripe')
                                <label class="block">
                                    <span class="field-label">Publishable key</span>
                                    <input type="password" wire:model="payments.{{ $index }}.credentials.publishable_key" placeholder="nemodificat">
                                </label>
                                <label class="block">
                                    <span class="field-label">Secret key</span>
                                    <input type="password" wire:model="payments.{{ $index }}.credentials.secret_key" placeholder="nemodificat">
                                </label>
                                <label class="block">
                                    <span class="field-label">Webhook signing secret</span>
                                    <input type="password" wire:model="payments.{{ $index }}.credentials.webhook_secret" placeholder="nemodificat">
                                </label>
                            @else
                                <label class="block">
                                    <span class="field-label">NETOPIA API key</span>
                                    <input type="password" wire:model="payments.{{ $index }}.credentials.api_key" placeholder="nemodificat">
                                </label>
                                <label class="block">
                                    <span class="field-label">POS signature</span>
                                    <input type="password" wire:model="payments.{{ $index }}.credentials.pos_signature" placeholder="nemodificat">
                                </label>
                                <label class="block md:col-span-2">
                                    <span class="field-label">Cheie publică IPN</span>
                                    <textarea wire:model="payments.{{ $index }}.credentials.ipn_public_key" rows="3"
                                              class="font-mono text-xs" placeholder="nemodificat"></textarea>
                                    {{-- Without it no notification is accepted at all, so the
                                         consequence of leaving it empty is stated here. --}}
                                    <span class="field-hint">
                                        Fără ea, notificările de plată sunt respinse. Vezi docs/netopia-ipn.md.
                                    </span>
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card-padded">
            <h2 class="mb-4 text-base font-semibold">Curieri</h2>

            <div class="space-y-4">
                @foreach($shippingProviders as $index => $provider)
                    <div class="rounded-lg border border-stone-200 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="font-medium">{{ $provider['name'] }}</span>
                                <span class="ml-2 {{ $provider['configured'] ? 'pill-positive' : 'pill-neutral' }}">
                                    {{ $provider['configured'] ? 'configurat' : 'neconfigurat' }}
                                </span>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="shippingProviders.{{ $index }}.is_active">
                                Activ
                            </label>
                        </div>

                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="block">
                                <span class="field-label">Mediu</span>
                                <select wire:model="shippingProviders.{{ $index }}.mode">
                                    <option value="sandbox">Sandbox</option>
                                    <option value="live">Live</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="field-label">Token API</span>
                                <input type="password" wire:model="shippingProviders.{{ $index }}.credentials.token" placeholder="nemodificat">
                            </label>
                            <label class="block">
                                <span class="field-label">Client ID</span>
                                <input wire:model="shippingProviders.{{ $index }}.credentials.client_id">
                            </label>
                            <label class="block">
                                <span class="field-label">Serviciu</span>
                                <input wire:model="shippingProviders.{{ $index }}.settings.service" placeholder="Standard">
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card-padded">
            <h2 class="mb-4 text-base font-semibold">Metode și tarife de livrare</h2>

            <div class="space-y-3">
                @foreach($shippingMethods as $index => $method)
                    <div class="grid items-end gap-3 rounded-lg border border-stone-200 p-4 md:grid-cols-6">
                        <label class="block md:col-span-2">
                            <span class="field-label">Denumire</span>
                            <input wire:model="shippingMethods.{{ $index }}.name">
                            @error('shippingMethods.'.$index.'.name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Tarif</span>
                            <input type="number" step="0.01" min="0" wire:model="shippingMethods.{{ $index }}.base_price">
                            @error('shippingMethods.'.$index.'.base_price') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Gratuit peste</span>
                            <input type="number" step="0.01" min="0" wire:model="shippingMethods.{{ $index }}.free_over">
                            @error('shippingMethods.'.$index.'.free_over') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Zile min</span>
                            <input type="number" min="0" wire:model="shippingMethods.{{ $index }}.estimated_days_min">
                        </label>

                        <div>
                            <label class="block">
                                <span class="field-label">Zile max</span>
                                <input type="number" min="0" wire:model="shippingMethods.{{ $index }}.estimated_days_max">
                            </label>
                            <label class="mt-2 flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="shippingMethods.{{ $index }}.is_active">
                                Activă
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <button type="submit" class="btn-primary">Salvează setările</button>
    </form>
</div>
