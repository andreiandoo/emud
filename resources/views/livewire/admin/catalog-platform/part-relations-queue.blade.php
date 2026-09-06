<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Part relation QA</h1>
            <p class="text-sm text-stone-500">Review unresolved cross-references and supersessions before they become canonical graph edges.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            @foreach($counts as $state => $count)
                <span class="rounded-full border bg-white px-3 py-1.5">{{ $state }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-lg bg-lime-100 p-3 text-sm text-lime-900">{{ session('status') }}</div>
    @endif

    <div class="grid gap-3 rounded-xl border bg-white p-4 md:grid-cols-4">
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-600">Status</span>
            <select wire:model.live="status" class="w-full rounded border px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach(['pending','ignored','resolved'] as $state)<option value="{{ $state }}">{{ $state }}</option>@endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-600">Relation type</span>
            <select wire:model.live="relationType" class="w-full rounded border px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($relationTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-600">Source</span>
            <select wire:model.live="sourceId" class="w-full rounded border px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->name }} ({{ $source->code }})</option>@endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs font-medium text-stone-600">Search</span>
            <input wire:model.live.debounce.300ms="search" class="w-full rounded border px-3 py-2 text-sm" placeholder="MPN, brand, target number">
        </label>
    </div>

    <div class="space-y-3">
        @forelse($relations as $relation)
            <div class="rounded-xl border bg-white p-4">
                <div class="grid gap-4 xl:grid-cols-[1fr_1fr_auto]">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-stone-500">Source part</div>
                        <a href="{{ route('admin.catalog-platform.parts.show', $relation->source_part_id) }}" class="mt-1 block font-semibold hover:underline">
                            {{ $relation->sourcePart?->brand?->name }} {{ $relation->sourcePart?->mpn_raw }}
                        </a>
                        <div class="mt-1 text-sm text-stone-500">{{ $relation->sourcePart?->name }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-stone-500">Requested target</div>
                        <div class="mt-1 font-semibold">{{ $relation->target_brand_raw ?: 'Any brand' }} · {{ $relation->target_scheme }} {{ $relation->target_number_raw }}</div>
                        <div class="mt-1 text-sm text-stone-500">
                            {{ $relation->relation_type }} · confidence {{ $relation->confidence ?? '—' }} · source {{ $relation->source?->code ?? 'unknown' }}
                        </div>
                        @if($relation->resolvedTargetPart)
                            <div class="mt-2 text-sm">Resolved to <a class="font-medium underline" href="{{ route('admin.catalog-platform.parts.show', $relation->resolved_target_part_id) }}">{{ $relation->resolvedTargetPart?->brand?->name }} {{ $relation->resolvedTargetPart?->mpn_raw }}</a></div>
                        @endif
                    </div>
                    <div class="flex min-w-52 flex-col gap-2">
                        @if($relation->status === 'pending')
                            <button wire:click="retry({{ $relation->id }})" class="rounded border px-3 py-2 text-sm">Retry automatic resolution</button>
                            <div class="flex gap-2">
                                <input wire:model="manualTargets.{{ $relation->id }}" class="min-w-0 flex-1 rounded border px-3 py-2 text-sm" placeholder="Target part ID / prt_…">
                                <button wire:click="manualResolve({{ $relation->id }})" class="rounded bg-stone-900 px-3 py-2 text-sm text-white">Resolve</button>
                            </div>
                            @error('manualTargets.'.$relation->id)<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                            <button wire:click="ignore({{ $relation->id }})" class="rounded border border-amber-300 px-3 py-2 text-sm text-amber-800">Ignore</button>
                        @elseif($relation->status === 'ignored')
                            <button wire:click="reopen({{ $relation->id }})" class="rounded border px-3 py-2 text-sm">Re-open</button>
                        @else
                            <span class="rounded bg-lime-50 px-3 py-2 text-center text-sm text-lime-800">Resolved</span>
                        @endif
                    </div>
                </div>

                <details class="mt-3 text-xs text-stone-500">
                    <summary class="cursor-pointer">Provenance / raw metadata</summary>
                    <div class="mt-2 grid gap-2 md:grid-cols-2">
                        <div>Source record: {{ $relation->catalog_source_record_id ?? '—' }}</div>
                        <div>Created: {{ $relation->created_at?->toIso8601String() }}</div>
                    </div>
                    <pre class="mt-2 overflow-auto rounded bg-stone-50 p-3">{{ json_encode($relation->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </div>
        @empty
            <div class="rounded-xl border border-dashed bg-white p-8 text-center text-stone-500">No part relations match these filters.</div>
        @endforelse
    </div>

    {{ $relations->links() }}
</div>
