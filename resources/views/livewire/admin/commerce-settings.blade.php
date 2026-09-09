<div class="space-y-6">
    @if(session('success'))
        <p class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    <form wire:submit="save" class="space-y-6">
        <x-admin.panel title="Procesatoare de plăți"
                       subtitle="Secretele sunt criptate în baza de date și nu se afișează înapoi — un câmp lăsat gol păstrează valoarea existentă.">
            <div class="space-y-4">
                @foreach($payments as $index => $provider)
                    <div class="rounded-xl bg-stone-50 p-5" wire:key="payment-{{ $index }}">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base font-semibold text-stone-900">{{ $provider['name'] }}</span>
                                <x-admin.status :label="$provider['configured'] ? 'Configurat' : 'Neconfigurat'"
                                                :tone="$provider['configured'] ? 'positive' : 'neutral'" />
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <label class="flex items-center gap-2 text-sm text-stone-700">
                                    <input type="checkbox" wire:model="payments.{{ $index }}.is_active">
                                    Activ
                                </label>

                                <x-admin.filter-chip wire:click="setDefaultPayment({{ $index }})" :active="$provider['is_default']">
                                    {{ $provider['is_default'] ? 'Implicit' : 'Setează implicit' }}
                                </x-admin.filter-chip>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
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
                                <label class="block md:col-span-3">
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
        </x-admin.panel>

        <x-admin.panel title="Curieri" subtitle="Conturile de curierat folosite pentru generarea AWB-urilor.">
            <div class="space-y-4">
                @foreach($shippingProviders as $index => $provider)
                    <div class="rounded-xl bg-stone-50 p-5" wire:key="carrier-{{ $index }}">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="text-base font-semibold text-stone-900">{{ $provider['name'] }}</span>
                                <x-admin.status :label="$provider['configured'] ? 'Configurat' : 'Neconfigurat'"
                                                :tone="$provider['configured'] ? 'positive' : 'neutral'" />
                            </div>

                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model="shippingProviders.{{ $index }}.is_active">
                                Activ
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-4">
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
        </x-admin.panel>

        <x-admin.panel title="Metode și tarife de livrare"
                       subtitle="Tarifele se aplică pe subtotalul coșului; „gratuit peste” lăsat gol înseamnă că metoda se taxează întotdeauna.">
            <div class="space-y-4">
                @foreach($shippingMethods as $index => $method)
                    <div class="grid items-start gap-4 rounded-xl bg-stone-50 p-5 md:grid-cols-6" wire:key="method-{{ $index }}">
                        <label class="block md:col-span-2">
                            <span class="field-label">Denumire</span>
                            <input wire:model="shippingMethods.{{ $index }}.name">
                            @error('shippingMethods.'.$index.'.name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="field-label">Tarif</span>
                            <x-admin.money-input wire:model="shippingMethods.{{ $index }}.base_price" />
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
                            <label class="mt-2 flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model="shippingMethods.{{ $index }}.is_active">
                                Activă
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.panel>

        <button type="submit" class="btn-primary">Salvează setările</button>
    </form>
</div>
