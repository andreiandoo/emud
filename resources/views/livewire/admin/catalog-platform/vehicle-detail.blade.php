<div class="space-y-6">
    <div>
        <a href="{{ route('admin.catalog-platform.explorer') }}" class="text-sm text-stone-500 hover:text-stone-900">← Catalog Explorer</a>
        <h1 class="mt-2 text-2xl font-bold">{{ $vehicle->generation?->model?->make?->name }} {{ $vehicle->generation?->model?->name }} {{ $vehicle->generation?->name }}</h1>
        <p class="text-stone-500">{{ $vehicle->commercial_name }} · {{ $vehicle->engine?->engine_code }} · {{ $vehicle->power_kw ? $vehicle->power_kw.' kW' : '' }}</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Production</div><div class="font-semibold">{{ $vehicle->production_from?->format('Y-m') ?? $vehicle->year }} — {{ $vehicle->production_to?->format('Y-m') ?? '—' }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Engine</div><div class="font-semibold">{{ $vehicle->engine?->engine_code ?? '—' }}</div><div class="text-sm text-stone-500">{{ $vehicle->displacement_cc }} cc · {{ $vehicle->fuel_type }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">EU TVV</div><div class="font-semibold">{{ $vehicle->eu_type_approval ?? '—' }}</div><div class="text-sm text-stone-500">{{ $vehicle->eu_type }} / {{ $vehicle->eu_variant }} / {{ $vehicle->eu_version }}</div></div>
        <div class="rounded-xl bg-white p-4"><div class="text-xs uppercase text-stone-400">Quality</div><div class="font-semibold">{{ $vehicle->quality_score ?? '—' }}</div></div>
    </div>
    <section class="rounded-xl border bg-white p-5"><h2 class="mb-3 font-bold">Compatible catalog parts</h2><div class="divide-y">@foreach($fitments as $fitment)<div class="py-3"><a href="{{ route('admin.catalog-platform.parts.show', $fitment->part) }}" class="font-semibold hover:underline">{{ $fitment->part?->brand?->name }} {{ $fitment->part?->mpn_raw }}</a><div class="text-sm text-stone-500">{{ $fitment->part?->name }} · {{ $fitment->part?->category?->full_path }} · {{ $fitment->position }} · confidence {{ $fitment->confidence ?? '—' }}</div>@foreach($fitment->constraints as $constraint)<div class="mt-1 text-xs text-amber-700">{{ $constraint->display_text ?: $constraint->constraint_type.': '.$constraint->value_text }}</div>@endforeach</div>@endforeach</div>{{ $fitments->links() }}</section>
</div>
