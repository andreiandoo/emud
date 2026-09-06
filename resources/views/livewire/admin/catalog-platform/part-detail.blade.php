<div class="space-y-6">
    <div>
        <a href="{{ route('admin.catalog-platform.explorer') }}" class="text-sm text-stone-500 hover:text-stone-900">← Catalog Explorer</a>
        <h1 class="mt-2 text-2xl font-bold">{{ $part->brand?->name }} {{ $part->mpn_raw }}</h1>
        <p class="text-stone-500">{{ $part->name }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Category</div><div class="mt-1 font-semibold">{{ $part->category?->full_path ?? '—' }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Quality</div><div class="mt-1 font-semibold">{{ $part->quality_score ?? '—' }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Numbers</div><div class="mt-1 font-semibold">{{ $part->numbers->count() }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Fitments</div><div class="mt-1 font-semibold">{{ $part->fitments->count() }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Graph QA</div><div class="mt-1 font-semibold">{{ $part->outgoingRelations->count() + $part->incomingRelations->count() }} edges · {{ $part->unresolvedRelations->where('status', 'pending')->count() }} pending</div></div>
    </div>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="mb-3 font-bold">Identifiers</h2>
        <div class="grid gap-2 md:grid-cols-2">
            @foreach($part->numbers as $number)
                <div class="rounded border p-3">
                    <div><span class="font-semibold">{{ $number->scheme }}</span> {{ $number->number_raw }} @if($number->oeMake)<span class="text-stone-500">· {{ $number->oeMake->name }}</span>@endif</div>
                    <div class="mt-1 text-xs text-stone-500">source {{ $number->source?->code ?? '—' }} · confidence {{ $number->confidence ?? '—' }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <h2 class="mb-3 font-bold">Vehicle fitments</h2>
        <div class="divide-y">
            @foreach($part->fitments as $fitment)
                <div class="py-3">
                    <a class="font-semibold hover:underline" href="{{ route('admin.catalog-platform.vehicles.show', $fitment->configuration) }}">{{ $fitment->configuration?->generation?->model?->make?->name }} {{ $fitment->configuration?->generation?->model?->name }} {{ $fitment->configuration?->generation?->name }}</a>
                    <div class="text-sm text-stone-500">{{ $fitment->position }} · confidence {{ $fitment->confidence ?? '—' }} · {{ $fitment->status }}</div>
                    @foreach($fitment->constraints as $constraint)<div class="mt-1 text-xs text-amber-700">{{ $constraint->display_text ?: $constraint->constraint_type.': '.$constraint->value_text }}</div>@endforeach
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-bold">Canonical relation graph</h2><p class="text-sm text-stone-500">Resolved equivalents, cross-references and supersession edges with source provenance.</p></div>
            @if($part->unresolvedRelations->where('status', 'pending')->isNotEmpty())
                <a href="{{ route('admin.catalog-platform.unresolved-relations', ['q' => $part->mpn_raw]) }}" class="rounded border px-3 py-2 text-sm">Open relation QA</a>
            @endif
        </div>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @foreach($part->outgoingRelations as $relation)
                <div class="rounded border p-3">
                    <div class="text-xs uppercase text-stone-400">Outgoing · {{ $relation->relation_type }}</div>
                    <a class="mt-1 block font-semibold hover:underline" href="{{ route('admin.catalog-platform.parts.show', $relation->targetPart) }}">{{ $relation->targetPart?->brand?->name }} {{ $relation->targetPart?->mpn_raw }}</a>
                    <div class="mt-1 text-xs text-stone-500">{{ $relation->is_directed ? 'directed' : 'undirected' }} · confidence {{ $relation->confidence ?? '—' }} · source {{ $relation->source?->code ?? '—' }}</div>
                </div>
            @endforeach
            @foreach($part->incomingRelations as $relation)
                <div class="rounded border p-3">
                    <div class="text-xs uppercase text-stone-400">Incoming · {{ $relation->relation_type }}</div>
                    <a class="mt-1 block font-semibold hover:underline" href="{{ route('admin.catalog-platform.parts.show', $relation->sourcePart) }}">{{ $relation->sourcePart?->brand?->name }} {{ $relation->sourcePart?->mpn_raw }}</a>
                    <div class="mt-1 text-xs text-stone-500">{{ $relation->is_directed ? 'directed' : 'undirected' }} · confidence {{ $relation->confidence ?? '—' }} · source {{ $relation->source?->code ?? '—' }}</div>
                </div>
            @endforeach
        </div>

        @if($part->outgoingRelations->isEmpty() && $part->incomingRelations->isEmpty())
            <div class="mt-4 rounded border border-dashed p-5 text-sm text-stone-500">No resolved canonical relations yet.</div>
        @endif

        @if($part->unresolvedRelations->isNotEmpty())
            <div class="mt-5 border-t pt-4">
                <h3 class="font-semibold">Unresolved source references</h3>
                <div class="mt-3 space-y-2">
                    @foreach($part->unresolvedRelations as $pending)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded bg-stone-50 p-3 text-sm">
                            <div><strong>{{ $pending->relation_type }}</strong> → {{ $pending->target_brand_raw }} {{ $pending->target_number_raw }} <span class="text-stone-500">({{ $pending->target_scheme }})</span></div>
                            <div class="text-xs text-stone-500">{{ $pending->status }} · attempts {{ $pending->resolution_attempts ?? 0 }} · source {{ $pending->source?->code ?? '—' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
