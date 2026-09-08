<div>
    <x-admin.page-header title="Catalog API"
                         subtitle="Consumatori, chei, identități externe și cote pentru API-ul tehnic /api/v1." />

    @if($issuedToken)
        {{-- Shown once and never again: the key is stored hashed, so there is no second chance to
             read it and the page says so before the operator navigates away. --}}
        <div class="mb-6 rounded-xl border-2 border-stone-900 bg-stone-50 p-5">
            <div class="font-semibold text-stone-900">Cheie nouă — copiaz-o acum</div>
            <p class="mt-1 text-sm text-stone-600">Nu mai poate fi afișată după ce închizi mesajul.</p>
            <code class="mt-3 block break-all rounded-lg border border-stone-200 bg-white p-3 font-mono text-sm">{{ $issuedToken }}</code>
            <button type="button" wire:click="$set('issuedToken', null)" class="btn-primary mt-3">Am salvat cheia</button>
        </div>
    @endif

    <form wire:submit="createConsumer" class="mb-8">
        <x-admin.panel title="Consumator nou" subtitle="Cota lunară de 0 înseamnă nelimitat local.">
            <div class="grid items-end gap-4 md:grid-cols-5">
                <label class="block">
                    <span class="field-label">Nume</span>
                    <input wire:model="name" required>
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Email</span>
                    <input type="email" wire:model="email">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Plan</span>
                    <select wire:model="plan">
                        @foreach(['basic', 'pro', 'ultra', 'mega', 'enterprise'] as $p)
                            <option>{{ $p }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="field-label">Cotă lunară</span>
                    <input type="number" min="0" wire:model="monthlyQuota">
                    @error('monthlyQuota') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="btn-primary">Creează consumer</button>
            </div>
        </x-admin.panel>
    </form>

    <div class="space-y-6">
        @forelse($consumers as $consumer)
            <x-admin.panel :title="$consumer->name" wire:key="consumer-{{ $consumer->id }}"
                           :subtitle="$consumer->plan.' · '.number_format($consumer->requests_used, 0, ',', '.').' / '.($consumer->monthly_quota > 0 ? number_format($consumer->monthly_quota, 0, ',', '.') : 'nelimitat local').' cereri'">
                <x-slot:actions>
                    <button type="button" wire:click="issueKey({{ $consumer->id }})" class="btn-primary">Emite cheie</button>
                    <button type="button" wire:click="toggleConsumer({{ $consumer->id }})" class="btn-secondary">
                        {{ $consumer->is_active ? 'Dezactivează' : 'Activează' }}
                    </button>
                </x-slot:actions>

                @if($consumer->externalIdentities->isNotEmpty())
                    <x-admin.section title="Identități externe">
                        <div class="space-y-2 rounded-xl bg-stone-50 p-4 text-sm">
                            @foreach($consumer->externalIdentities as $identity)
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="font-medium text-stone-900">{{ $identity->provider }}</span>
                                    <code class="break-all font-mono text-xs text-stone-600">{{ $identity->external_user_id }}</code>
                                    <span class="text-stone-600">{{ $identity->subscription ?? '—' }}</span>
                                    <span class="text-stone-400">{{ $identity->last_seen_at?->diffForHumans() ?? 'niciodată' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-admin.section>
                @endif

                <x-admin.section title="Chei native">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Nume</th>
                                <th>Prefix</th>
                                <th>Ultima utilizare</th>
                                <th>Status</th>
                                <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($consumer->keys as $key)
                                <tr wire:key="key-{{ $key->id }}">
                                    <td class="font-medium text-stone-900">{{ $key->name }}</td>
                                    <td><code class="font-mono text-xs text-stone-600">{{ $key->key_prefix }}…</code></td>
                                    <td class="text-stone-500">{{ $key->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        <x-admin.status :label="$key->revoked_at ? 'Revocată' : 'Activă'"
                                                        :tone="$key->revoked_at ? 'neutral' : 'positive'" />
                                    </td>
                                    <td>
                                        @unless($key->revoked_at)
                                            <x-admin.row-actions>
                                                <x-admin.row-action tone="danger" wire:click="revokeKey({{ $key->id }})"
                                                                    wire:confirm="Revoci cheia? Cererile cu ea vor fi respinse imediat.">Revocă</x-admin.row-action>
                                            </x-admin.row-actions>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-stone-500">
                                        Fără chei native. Consumerul poate fi exclusiv extern (de ex. RapidAPI).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-admin.section>
            </x-admin.panel>
        @empty
            <x-admin.empty title="Niciun consumator" hint="Creează un consumator pentru a emite chei de API." />
        @endforelse
    </div>
</div>
