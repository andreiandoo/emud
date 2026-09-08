<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Unresolved part relations</h1>
            <p class="mt-1 text-sm text-stone-500">Cross-references and supersessions waiting for a canonical target.</p>
        </div>
        <button wire:click="retryPending" class="btn-primary">Retry up to 500 pending</button>
    </div>

    @if(session('status'))
        <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['pending' => 'Pending', 'resolved' => 'Resolved', 'rejected' => 'Rejected'] as $key => $label)
            <button wire:click="$set('status', '{{ $key }}')" class="rounded-xl border bg-white p-4 text-left {{ $status === $key ? 'ring-2 ring-stone-900' : '' }}">
                <div class="text-xs uppercase tracking-wide text-stone-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tracking-tight">{{ number_format((int) ($counts[$key] ?? 0)) }}</div>
            </button>
        @endforeach
    </div>

    <section class="grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-4">
        <label class="space-y-1 md:col-span-2">
            <span class="text-xs font-medium text-stone-500">Search target/source number, brand or part name</span>
            <input wire:model.live.debounce.300ms="q"  placeholder="e.g. 90915-YZZD2, MANN, OC 123">
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-500">Relation type</span>
            <select wire:model.live="relationType" >
                <option value="">All</option>
                @foreach($relationTypes as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-500">Source</span>
            <select wire:model.live="sourceId" >
                <option value="">All</option>
                @foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->code }} · {{ $source->name }}</option>@endforeach
            </select>
        </label>
    </section>

    <div class="space-y-4">
        @forelse($relations as $relation)
            <article class="card-padded">
                <div class="grid gap-5 xl:grid-cols-[1.2fr_1fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-stone-100 px-2 py-1 text-xs font-semibold">{{ $relation->relation_type }}</span>
                            <span class="rounded px-2 py-1 text-xs {{ $relation->status === 'pending' ? 'bg-amber-100 text-amber-900' : ($relation->status === 'resolved' ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900') }}">{{ $relation->status }}</span>
                            <span class="text-xs text-stone-500">confidence {{ $relation->confidence ?? '—' }}%</span>
                        </div>
                        <div class="mt-3 text-sm text-stone-500">Source part</div>
                        <div class="font-semibold">
                            {{ $relation->sourcePart?->brand?->name ?? 'Unknown brand' }}
                            {{ $relation->sourcePart?->mpn_raw ?? '#'.$relation->source_part_id }}
                        </div>
                        <div class="text-sm text-stone-600">{{ $relation->sourcePart?->name }}</div>
                        <div class="mt-2 text-xs text-stone-500">
                            {{ $relation->source?->code ?? 'No source' }}
                            @if($relation->sourceRecord) · source record #{{ $relation->sourceRecord->id }}@endif
                        </div>
                    </div>

                    <div>
                        <div class="text-sm text-stone-500">Requested target</div>
                        <div class="mt-1 font-mono text-base font-semibold">{{ $relation->target_scheme }} · {{ $relation->target_number_raw }}</div>
                        <div class="text-sm text-stone-600">{{ $relation->target_brand_raw ?: 'Brand not supplied' }}</div>
                        <div class="mt-3 text-xs text-stone-500">
                            attempts {{ $relation->resolution_attempts ?? 0 }}
                            @if($relation->last_resolution_attempt_at) · last {{ $relation->last_resolution_attempt_at->diffForHumans() }}@endif
                        </div>
                        @if($relation->resolvedTargetPart)
                            <div class="mt-3 rounded bg-emerald-50 p-3 text-sm text-emerald-950">
                                Resolved to {{ $relation->resolvedTargetPart->brand?->name }} {{ $relation->resolvedTargetPart->mpn_raw }}
                                · prt_{{ $relation->resolvedTargetPart->public_id }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-64 space-y-2">
                        @if($relation->status === 'pending')
                            <button wire:click="retry({{ $relation->id }})" class="text-sm">Retry automatic match</button>
                            <input wire:model="manualTargets.{{ $relation->id }}" class="font-mono text-xs" placeholder="prt_<public_id> or numeric ID">
                            @error('manualTargets.'.$relation->id)<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                            <textarea wire:model="reviewNotes.{{ $relation->id }}" rows="2" class="w-full text-xs" placeholder="Optional review note"></textarea>
                            <button wire:click="link({{ $relation->id }})" class="btn-primary">Link canonical target</button>
                            <button wire:click="reject({{ $relation->id }})" wire:confirm="Reject this reference from the resolution queue?" class="w-full rounded border border-red-200 px-3 py-2 text-sm text-red-700">Reject reference</button>
                        @elseif($relation->status === 'rejected')
                            <textarea wire:model="reviewNotes.{{ $relation->id }}" rows="2" class="w-full text-xs" placeholder="Reason for reopening"></textarea>
                            <button wire:click="reopen({{ $relation->id }})" class="text-sm">Reopen</button>
                        @endif
                    </div>
                </div>

                @if($relation->review_note || $relation->reviewer)
                    <div class="mt-4 border-t pt-3 text-xs text-stone-500">
                        Reviewed @if($relation->reviewer) by {{ $relation->reviewer->name ?? $relation->reviewer->email }} @endif
                        @if($relation->reviewed_at) on {{ $relation->reviewed_at->format('Y-m-d H:i') }} @endif
                        @if($relation->review_note) · {{ $relation->review_note }} @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-10 text-center text-stone-500">No unresolved relations match these filters.</div>
        @endforelse
    </div>

    {{ $relations->links() }}
</div>
