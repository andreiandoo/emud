<div>
    <x-admin.page-header title="Retururi" subtitle="Cereri de retur și starea lor." />

    @if($error)
        <p class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $error }}</p>
    @endif

    <div class="mb-5 flex flex-wrap gap-2">
        <x-admin.filter-chip wire:click="$set('status', '')" :active="$status === ''">Toate</x-admin.filter-chip>

        @foreach($statuses as $option)
            <x-admin.filter-chip wire:click="$set('status', '{{ $option->value }}')"
                                 :active="$status === $option->value"
                                 :count="$counts[$option->value] ?? 0">{{ $option->label() }}</x-admin.filter-chip>
        @endforeach
    </div>

    @if($returns->isEmpty())
        <x-admin.empty title="Niciun retur" hint="Cererile de retur trimise de clienți apar aici." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Comandă</th>
                        <th>Client</th>
                        <th>Motiv</th>
                        <th>Produse</th>
                        <th>Stare</th>
                        <th>Cerut</th>
                        <th class="w-10"><span class="sr-only">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($returns as $return)
                        <tr wire:key="return-{{ $return->id }}">
                            <td>
                                @if($return->order)
                                    <a href="{{ route('admin.orders.edit', $return->order) }}" class="font-semibold text-stone-900 hover:underline">{{ $return->order->number }}</a>
                                @else
                                    <span class="text-stone-400">—</span>
                                @endif
                            </td>

                            <td>
                                <div class="flex items-center gap-2.5">
                                    <x-admin.avatar :name="$return->user?->name ?: $return->order?->customer_email ?: ''" size="sm" />
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-stone-900">{{ $return->user?->name ?: '—' }}</div>
                                        <div class="truncate text-xs text-stone-500">{{ $return->order?->customer_email }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="text-stone-600">{{ $return->reason->label() }}</td>

                            <td>
                                <ul class="space-y-0.5 text-xs text-stone-600">
                                    @foreach($return->items as $line)
                                        <li>{{ $line->orderItem?->name }} <span class="text-stone-400">×{{ $line->quantity }}</span></li>
                                    @endforeach
                                </ul>
                            </td>

                            <td><span class="{{ $return->status->pillClass() }}">{{ $return->status->label() }}</span></td>

                            <td class="whitespace-nowrap text-stone-500">{{ $return->requested_at?->format('d.m.Y') }}</td>

                            <td>
                                @if(count($return->status->allowedNext()) > 0)
                                    <x-admin.row-actions>
                                        @foreach($return->status->allowedNext() as $next)
                                            <x-admin.row-action wire:click="advance({{ $return->id }}, '{{ $next->value }}')"
                                                                :tone="$next->value === 'rejected' ? 'danger' : 'default'">{{ $next->label() }}</x-admin.row-action>
                                        @endforeach
                                    </x-admin.row-actions>
                                @else
                                    <span class="block text-right text-xs text-stone-400">închis</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $returns->links() }}</div>
    @endif
</div>
