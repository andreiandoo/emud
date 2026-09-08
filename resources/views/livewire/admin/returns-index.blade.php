<div>
    <x-admin.page-header title="Retururi" subtitle="Cereri de retur și starea lor." />

    @if($error)
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $error }}</p>
    @endif

    <div class="mb-5 flex flex-wrap gap-2">
        <button wire:click="$set('status', '')" @class([
            'rounded-full border px-3 py-1 text-sm',
            'border-stone-900 bg-stone-900 text-white' => $status === '',
            'border-stone-300 bg-white hover:border-stone-900' => $status !== '',
        ])>Toate</button>

        @foreach($statuses as $option)
            <button wire:click="$set('status', '{{ $option->value }}')" @class([
                'rounded-full border px-3 py-1 text-sm',
                'border-stone-900 bg-stone-900 text-white' => $status === $option->value,
                'border-stone-300 bg-white hover:border-stone-900' => $status !== $option->value,
            ])>
                {{ $option->label() }}
                <span class="opacity-60">{{ $counts[$option->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    @if($returns->isEmpty())
        <x-admin.empty title="Niciun retur" hint="Cererile de retur trimise de clienți apar aici." />
    @else
        <div class="card overflow-x-auto">
            <table>
                <thead>
                    <tr>
                        <th>Comandă</th>
                        <th>Client</th>
                        <th>Motiv</th>
                        <th>Produse</th>
                        <th>Stare</th>
                        <th>Cerut</th>
                        <th class="text-right">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($returns as $return)
                        <tr>
                            <td class="font-medium">{{ $return->order?->number }}</td>
                            <td>{{ $return->user?->name ?? $return->order?->customer_email }}</td>
                            <td>{{ $return->reason->label() }}</td>
                            <td>
                                <ul class="space-y-0.5 text-xs text-stone-600">
                                    @foreach($return->items as $line)
                                        <li>{{ $line->orderItem?->name }} × {{ $line->quantity }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td><span class="{{ $return->status->pillClass() }}">{{ $return->status->label() }}</span></td>
                            <td class="text-xs text-stone-500">{{ $return->requested_at?->format('d.m.Y') }}</td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    @forelse($return->status->allowedNext() as $next)
                                        <button wire:click="advance({{ $return->id }}, '{{ $next->value }}')"
                                                class="rounded-lg border border-stone-300 px-2.5 py-1 text-xs font-semibold hover:border-stone-900">
                                            {{ $next->label() }}
                                        </button>
                                    @empty
                                        <span class="text-xs text-stone-400">închis</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $returns->links() }}</div>
    @endif
</div>
