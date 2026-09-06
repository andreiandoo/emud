<div class="space-y-6">
    <div>
        <a href="{{ route('admin.catalog-platform.explorer') }}" class="text-sm text-stone-500 hover:text-stone-900">← Catalog Explorer</a>
        <h1 class="mt-2 text-2xl font-bold">{{ $part->brand?->name }} {{ $part->mpn_raw }}</h1>
        <p class="text-stone-500">{{ $part->name }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Category</div><div class="mt-1 font-semibold">{{ $part->category?->full_path ?? '—' }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Quality</div><div class="mt-1 font-semibold">{{ $part->quality_score ?? '—' }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Numbers</div><div class="mt-1 font-semibold">{{ $part->numbers->count() }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Fitments</div><div class="mt-1 font-semibold">{{ $part->fitments->count() }}</div></div>
    </div>

    <section class="rounded-xl border bg-white p-5"><h2 class="mb-3 font-bold">Identifiers</h2><div class="grid gap-2 md:grid-cols-2">@foreach($part->numbers as $number)<div class="rounded border p-3"><span class="font-semibold">{{ $number->scheme }}</span> {{ $number->number_raw }} @if($number->oeMake)<span class="text-stone-500">· {{ $number->oeMake->name }}</span>@endif</div>@endforeach</div></section>

    <section class="rounded-xl border bg-white p-5"><h2 class="mb-3 font-bold">Vehicle fitments</h2><div class="divide-y">@foreach($part->fitments as $fitment)<div class="py-3"><a class="font-semibold hover:underline" href="{{ route('admin.catalog-platform.vehicles.show', $fitment->configuration) }}">{{ $fitment->configuration?->generation?->model?->make?->name }} {{ $fitment->configuration?->generation?->model?->name }} {{ $fitment->configuration?->generation?->name }}</a><div class="text-sm text-stone-500">{{ $fitment->position }} · confidence {{ $fitment->confidence ?? '—' }} · {{ $fitment->status }}</div>@foreach($fitment->constraints as $constraint)<div class="mt-1 text-xs text-amber-700">{{ $constraint->display_text ?: $constraint->constraint_type.': '.$constraint->value_text }}</div>@endforeach</div>@endforeach</div></section>

    <section class="rounded-xl border bg-white p-5"><h2 class="mb-3 font-bold">Relations</h2><div class="space-y-2">@foreach($part->outgoingRelations as $relation)<div>{{ $relation->relation_type }} → <a class="font-semibold hover:underline" href="{{ route('admin.catalog-platform.parts.show', $relation->targetPart) }}">{{ $relation->targetPart?->brand?->name }} {{ $relation->targetPart?->mpn_raw }}</a></div>@endforeach @foreach($part->incomingRelations as $relation)<div><a class="font-semibold hover:underline" href="{{ route('admin.catalog-platform.parts.show', $relation->sourcePart) }}">{{ $relation->sourcePart?->brand?->name }} {{ $relation->sourcePart?->mpn_raw }}</a> → {{ $relation->relation_type }}</div>@endforeach</div></section>
</div>
