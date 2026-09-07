<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Catalog API</h1>
        <p class="text-sm text-stone-500">Consumers, chei, identități externe și cote pentru API-ul tehnic /api/v1.</p>
    </div>

    @if($issuedToken)
        <div class="rounded-xl border-2 border-lime-400 bg-lime-50 p-5">
            <div class="font-bold">Cheie nouă — copiaz-o acum</div>
            <code class="mt-2 block break-all rounded bg-white p-3">{{ $issuedToken }}</code>
            <button wire:click="$set('issuedToken', null)" class="mt-3 rounded bg-stone-900 px-3 py-2 text-sm text-white">Am salvat cheia</button>
        </div>
    @endif

    <form wire:submit="createConsumer" class="grid gap-3 rounded-xl bg-white p-5 shadow-sm md:grid-cols-5">
        <input wire:model="name" class="rounded border px-3 py-2" placeholder="Nume" required>
        <input wire:model="email" class="rounded border px-3 py-2" placeholder="Email">
        <select wire:model="plan" class="rounded border px-3 py-2">
            @foreach(['basic','pro','ultra','mega','enterprise'] as $p)
                <option>{{ $p }}</option>
            @endforeach
        </select>
        <input wire:model="monthlyQuota" type="number" min="0" class="rounded border px-3 py-2" placeholder="Quota">
        <button class="rounded bg-lime-400 px-4 py-2 font-semibold">Creează consumer</button>
    </form>

    <div class="space-y-4">
        @foreach($consumers as $consumer)
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-lg font-bold">{{ $consumer->name }}</div>
                        <div class="text-sm text-stone-500">
                            {{ $consumer->plan }} · {{ number_format($consumer->requests_used) }} /
                            {{ $consumer->monthly_quota > 0 ? number_format($consumer->monthly_quota) : 'nelimitat local' }} requests
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="issueKey({{ $consumer->id }})" class="rounded bg-stone-950 px-3 py-2 text-sm text-white">Emite cheie</button>
                        <button wire:click="toggleConsumer({{ $consumer->id }})" class="rounded border px-3 py-2 text-sm">{{ $consumer->is_active ? 'Dezactivează' : 'Activează' }}</button>
                    </div>
                </div>

                @if($consumer->externalIdentities->isNotEmpty())
                    <div class="mt-4 rounded-lg bg-stone-50 p-3">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Identități externe</div>
                        <div class="space-y-2 text-sm">
                            @foreach($consumer->externalIdentities as $identity)
                                <div class="flex flex-wrap gap-x-3 gap-y-1">
                                    <span class="font-semibold">{{ $identity->provider }}</span>
                                    <code class="break-all">{{ $identity->external_user_id }}</code>
                                    <span>{{ $identity->subscription ?? '—' }}</span>
                                    <span class="text-stone-500">{{ $identity->last_seen_at?->diffForHumans() ?? 'niciodată' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-stone-500">
                                <th class="py-2">Nume</th>
                                <th>Prefix</th>
                                <th>Ultima utilizare</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($consumer->keys as $key)
                                <tr class="border-t">
                                    <td class="py-2">{{ $key->name }}</td>
                                    <td><code>{{ $key->key_prefix }}…</code></td>
                                    <td>{{ $key->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td>{{ $key->revoked_at ? 'revoked' : 'active' }}</td>
                                    <td class="text-right">
                                        @unless($key->revoked_at)
                                            <button wire:click="revokeKey({{ $key->id }})" class="text-red-700 underline">Revocă</button>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td colspan="5" class="py-3 text-stone-500">Fără chei native. Consumerul poate fi exclusiv extern (de ex. RapidAPI).</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
