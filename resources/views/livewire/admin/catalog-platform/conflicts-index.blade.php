<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Catalog conflicts</h1>
        <p class="mt-1 text-sm text-stone-500">Review contradictory assertions and canonicalization decisions without deleting source evidence.</p>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $key => $label)
            <button wire:click="$set('status', '{{ $key }}')" class="rounded-xl border bg-white p-4 text-left {{ $status === $key ? 'ring-2 ring-lime-400' : '' }}">
                <div class="text-xs uppercase tracking-wide text-stone-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold">{{ number_format((int) ($counts[$key] ?? 0)) }}</div>
            </button>
        @endforeach
    </div>

    <section class="grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-4">
        <label class="space-y-1 md:col-span-2">
            <span class="text-xs font-medium text-stone-500">Search entity, relation or conflict details</span>
            <input wire:model.live.debounce.300ms="q" class="w-full rounded border px-3 py-2" placeholder="technical_identity_mismatch, fitment, part number…">
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-500">Entity type</span>
            <select wire:model.live="type" class="w-full rounded border px-3 py-2">
                <option value="">All</option>
                @foreach($types as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-500">Severity</span>
            <select wire:model.live="severity" class="w-full rounded border px-3 py-2">
                <option value="">All</option>
                @foreach(['error', 'warning', 'info'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>
    </section>

    <div class="space-y-4">
        @forelse($conflicts as $conflict)
            <article class="rounded-xl border bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong>{{ $conflict->entity_type }} #{{ $conflict->entity_id }}</strong>
                            <span class="rounded bg-stone-100 px-2 py-1 text-xs">{{ $conflict->field_or_relation }}</span>
                            <span class="rounded px-2 py-1 text-xs {{ $conflict->severity === 'error' ? 'bg-red-100 text-red-900' : ($conflict->severity === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-stone-100') }}">{{ $conflict->severity }}</span>
                            <span class="text-xs text-stone-500">{{ $conflict->status }}</span>
                        </div>
                        <pre class="mt-3 max-h-80 overflow-auto rounded bg-stone-50 p-3 text-xs">{{ json_encode($conflict->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @if($conflict->resolution)
                            <div class="mt-3 rounded border bg-white p-3 text-xs text-stone-600">
                                <strong>Resolution record</strong>
                                <pre class="mt-2 overflow-auto">{{ json_encode($conflict->resolution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    </div>

                    <div class="w-full space-y-2 sm:w-72">
                        <textarea wire:model="notes.{{ $conflict->id }}" rows="3" class="w-full rounded border p-2 text-xs" placeholder="Review note / reason"></textarea>
                        @if($conflict->status === 'open')
                            <button wire:click="resolve({{ $conflict->id }}, 'keep_canonical')" class="w-full rounded bg-lime-400 px-3 py-2 text-sm font-semibold text-stone-950">Keep canonical value</button>
                            <button wire:click="resolve({{ $conflict->id }}, 'accept_source')" class="w-full rounded border px-3 py-2 text-sm">Accept source decision</button>
                            <button wire:click="resolve({{ $conflict->id }}, 'not_a_conflict')" class="w-full rounded border px-3 py-2 text-sm">Mark not a conflict</button>
                            <button wire:click="dismiss({{ $conflict->id }})" wire:confirm="Dismiss this conflict?" class="w-full rounded border border-red-200 px-3 py-2 text-sm text-red-700">Dismiss</button>
                        @else
                            <button wire:click="reopen({{ $conflict->id }})" class="w-full rounded border px-3 py-2 text-sm">Reopen conflict</button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-stone-500">No conflicts match these filters.</div>
        @endforelse
    </div>

    {{ $conflicts->links() }}
</div>
