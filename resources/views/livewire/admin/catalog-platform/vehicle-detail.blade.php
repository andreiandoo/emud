<div>
    <a href="{{ route('admin.catalog-platform.explorer') }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">← Catalog Explorer</a>

    <x-admin.page-header
        :title="trim($vehicle->generation?->model?->make?->name.' '.$vehicle->generation?->model?->name.' '.$vehicle->generation?->name)"
        :subtitle="collect([$vehicle->commercial_name, $vehicle->engine?->engine_code, $vehicle->power_kw ? $vehicle->power_kw.' kW' : null])->filter()->implode(' · ')" />

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="($vehicle->production_from?->format('Y-m') ?? $vehicle->year).' — '.($vehicle->production_to?->format('Y-m') ?? '—')"
                      label="Producție" />
        <x-admin.stat :value="$vehicle->engine?->engine_code ?? '—'"
                      :label="trim($vehicle->displacement_cc.' cc · '.$vehicle->fuel_type, ' ·')" />
        <x-admin.stat :value="$vehicle->eu_type_approval ?? '—'"
                      :label="'TVV '.$vehicle->eu_type.' / '.$vehicle->eu_variant.' / '.$vehicle->eu_version" />
        <x-admin.stat :value="$vehicle->quality_score ?? '—'" label="Scor de calitate" />
    </div>

    <x-admin.section title="Piese compatibile din catalog">
        @if($fitments->isEmpty())
            <x-admin.empty title="Nicio piesă legată" hint="Nu există fitment-uri pentru această configurație." />
        @else
            <ul class="divide-y divide-stone-100">
                @foreach($fitments as $fitment)
                    <li class="py-3" wire:key="vehicle-fitment-{{ $fitment->id }}">
                        <a href="{{ route('admin.catalog-platform.parts.show', $fitment->part) }}" class="font-medium text-stone-900 hover:underline">
                            {{ $fitment->part?->brand?->name }} {{ $fitment->part?->mpn_raw }}
                        </a>
                        <div class="text-sm text-stone-500">
                            {{ $fitment->part?->name }} · {{ $fitment->part?->category?->full_path }} ·
                            {{ $fitment->position }} · încredere {{ $fitment->confidence ?? '—' }}
                        </div>

                        @foreach($fitment->constraints as $constraint)
                            <div class="mt-1 text-xs text-amber-700">
                                {{ $constraint->display_text ?: $constraint->constraint_type.': '.$constraint->value_text }}
                            </div>
                        @endforeach
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $fitments->links() }}</div>
        @endif
    </x-admin.section>
</div>
