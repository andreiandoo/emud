<div>
    <x-admin.page-header title="Catalog Explorer"
                         subtitle="Caută din orice direcție: vehicul, OE/OEM/IAM, MPN, fitment, sursă sau conflict." />

    <div class="mb-8 space-y-4 rounded-xl bg-stone-100 p-5">
        <div class="grid gap-3 lg:grid-cols-[14rem_1fr_auto]">
            <label class="block">
                <span class="sr-only">Ce se caută</span>
                <select wire:model.live="mode">
                    <option value="everything">Tot catalogul</option>
                    <option value="vehicles">Vehicule</option>
                    <option value="parts">Piese</option>
                    <option value="numbers">OE / MPN / IAM / EAN</option>
                    <option value="identifiers">Identificatori vehicul</option>
                    <option value="fitments">Fitments</option>
                    <option value="sources">Surse</option>
                    <option value="conflicts">Conflicte / QA</option>
                </select>
            </label>

            <label class="relative block">
                <span class="sr-only">Termen de căutare</span>
                <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <input wire:model.live.debounce.300ms="search" autofocus class="pl-9"
                       placeholder="VIN, OE, MPN, EAN, marcă, model, motor, piesă...">
            </label>

            <button type="button" wire:click="clearFilters" class="btn-secondary">Reset filtre</button>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <select wire:model.live="make" aria-label="Marcă auto">
                <option value="">Marcă auto</option>
                @foreach($makes as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
            </select>

            <select wire:model.live="model" aria-label="Model" @disabled(! $make)>
                <option value="">Model</option>
                @foreach($models as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
            </select>

            <select wire:model.live="generation" aria-label="Generație" @disabled(! $model)>
                <option value="">Generație</option>
                @foreach($generations as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->year_from }}–{{ $item->year_to ?? '...' }})</option>@endforeach
            </select>

            <input wire:model.live.debounce.300ms="year" type="number" min="1900" max="2100" placeholder="An" aria-label="An">

            <select wire:model.live="fuel" aria-label="Combustibil">
                <option value="">Combustibil</option>
                @foreach($fuels as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>

            <select wire:model.live="category" aria-label="Categorie piesă">
                <option value="">Categorie piesă</option>
                @foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->full_path ?: $item->name }}</option>@endforeach
            </select>

            <select wire:model.live="brand" aria-label="Brand piesă">
                <option value="">Brand piesă</option>
                @foreach($brands as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
            </select>

            <select wire:model.live="source" aria-label="Sursă date">
                <option value="">Sursă date</option>
                @foreach($sources as $item)<option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }}</option>@endforeach
            </select>

            <select wire:model.live="position" aria-label="Poziție">
                <option value="">Poziție</option>
                @foreach($positions as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>

            <input wire:model.live.debounce.300ms="minConfidence" type="number" min="0" max="100" step="1"
                   placeholder="Confidence minim" aria-label="Confidence minim">
        </div>
    </div>

    <div class="space-y-8">
        @if($mode === 'sources')
            <x-admin.section title="Surse">
                <x-slot:aside>{{ $sourcesFound->count() }}</x-slot:aside>

                <ul class="divide-y divide-stone-100">
                    @foreach($sourcesFound as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <a href="{{ route('admin.catalog-platform.sources.edit', $item) }}" class="font-medium text-stone-900 hover:underline">{{ $item->code }} · {{ $item->name }}</a>
                                <div class="text-sm text-stone-500">
                                    {{ $item->source_type }} · {{ number_format($item->records_count, 0, ',', '.') }} înregistrări brute · {{ $item->import_runs_count }} rulări
                                </div>
                            </div>

                            <a href="{{ route('admin.catalog-platform.source-records', $item) }}" class="btn-ghost">Înregistrări brute</a>
                        </li>
                    @endforeach
                </ul>
            </x-admin.section>
        @endif

        @if($mode === 'conflicts')
            <x-admin.section title="Conflicte">
                <x-slot:aside>{{ $conflicts->count() }}</x-slot:aside>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr><th>Entitate</th><th>Câmp / relație</th><th>Severitate</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($conflicts as $item)
                            <tr wire:key="explorer-conflict-{{ $item->id }}">
                                <td class="font-medium text-stone-900">{{ $item->entity_type }} #{{ $item->entity_id }}</td>
                                <td class="font-mono text-xs text-stone-600">{{ $item->field_or_relation }}</td>
                                <td>
                                    <x-admin.status :label="$item->severity" :tone="match ($item->severity) {
                                        'error' => 'danger',
                                        'warning' => 'warning',
                                        default => 'neutral',
                                    }" />
                                </td>
                                <td class="text-stone-600">{{ $item->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.section>
        @endif

        @if($fitments->isNotEmpty())
            <x-admin.section title="Fitments">
                <x-slot:aside>{{ $fitments->count() }}</x-slot:aside>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Vehicul</th>
                                <th>Piesă</th>
                                <th>Categorie</th>
                                <th>Poziție</th>
                                <th class="text-right">Confidence</th>
                                <th>Sursă</th>
                                <th class="text-right">Condiții</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fitments as $fitment)
                                <tr wire:key="fitment-{{ $fitment->id }}">
                                    <td>
                                        <a href="{{ route('admin.catalog-platform.vehicles.show', $fitment->configuration) }}" class="text-stone-900 hover:underline">
                                            {{ $fitment->configuration?->generation?->model?->make?->name }}
                                            {{ $fitment->configuration?->generation?->model?->name }}
                                            {{ $fitment->configuration?->year }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.catalog-platform.parts.show', $fitment->part) }}" class="font-medium text-stone-900 hover:underline">
                                            {{ $fitment->part?->brand?->name }} {{ $fitment->part?->mpn_raw }}
                                        </a>
                                    </td>
                                    <td class="text-stone-600">{{ $fitment->part?->category?->name }}</td>
                                    <td class="text-stone-600">{{ $fitment->position }}</td>
                                    <td class="text-right tabular-nums">{{ $fitment->confidence }}</td>
                                    <td class="font-mono text-xs text-stone-500">{{ $fitment->source?->code }}</td>
                                    <td class="text-right tabular-nums">{{ $fitment->constraints->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.section>
        @endif

        @if($vehicles->isNotEmpty() || $parts->isNotEmpty() || $numbers->isNotEmpty())
            <div class="grid gap-8 xl:grid-cols-2">
                @if($vehicles->isNotEmpty())
                    <x-admin.section title="Vehicule">
                        <x-slot:aside>{{ $vehicles->count() }}</x-slot:aside>

                        <ul class="divide-y divide-stone-100">
                            @foreach($vehicles as $vehicle)
                                <li>
                                    <a href="{{ route('admin.catalog-platform.vehicles.show', $vehicle) }}" class="-mx-3 block rounded-lg px-3 py-3 transition hover:bg-stone-50">
                                        <div class="font-medium text-stone-900">
                                            {{ $vehicle->generation?->model?->make?->name }}
                                            {{ $vehicle->generation?->model?->name }}
                                            {{ $vehicle->generation?->name }}
                                        </div>
                                        <div class="text-sm text-stone-500">
                                            {{ $vehicle->year }} · {{ $vehicle->commercial_name }} ·
                                            {{ $vehicle->engine?->engine_code ?: $vehicle->engine?->name }}
                                            @if($vehicle->power_kw) · {{ $vehicle->power_kw }} kW @endif
                                            · TVV {{ $vehicle->eu_type }}/{{ $vehicle->eu_variant }}/{{ $vehicle->eu_version }}
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-admin.section>
                @endif

                @if($parts->isNotEmpty())
                    <x-admin.section title="Piese">
                        <x-slot:aside>{{ $parts->count() }}</x-slot:aside>

                        <ul class="divide-y divide-stone-100">
                            @foreach($parts as $part)
                                <li>
                                    <a href="{{ route('admin.catalog-platform.parts.show', $part) }}" class="-mx-3 block rounded-lg px-3 py-3 transition hover:bg-stone-50">
                                        <div class="font-medium text-stone-900">{{ $part->brand?->name }} {{ $part->mpn_raw }}</div>
                                        <div class="text-sm text-stone-500">{{ $part->name }} · {{ $part->category?->full_path }}</div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-admin.section>
                @endif

                @if($numbers->isNotEmpty())
                    <x-admin.section title="Identificatori / numere" class="xl:col-span-2">
                        <x-slot:aside>{{ $numbers->count() }}</x-slot:aside>

                        <ul class="divide-y divide-stone-100">
                            @foreach($numbers as $number)
                                <li class="py-3">
                                    @if($number instanceof \App\Models\CatalogPartNumber)
                                        <a href="{{ route('admin.catalog-platform.parts.show', $number->part) }}" class="font-medium text-stone-900 hover:underline">
                                            {{ $number->scheme }}: {{ $number->number_raw }}
                                        </a>
                                        <div class="text-sm text-stone-500">
                                            {{ $number->oeMake?->name }} · {{ $number->part?->brand?->name }} {{ $number->part?->mpn_raw }} · {{ $number->source?->code }}
                                        </div>
                                    @else
                                        <a href="{{ route('admin.catalog-platform.vehicles.show', $number->configuration) }}" class="font-medium text-stone-900 hover:underline">
                                            {{ $number->scheme }}: {{ $number->value_raw }}
                                        </a>
                                        <div class="text-sm text-stone-500">{{ $number->source?->code }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-admin.section>
                @endif
            </div>
        @elseif($shouldQuery && ! in_array($mode, ['sources', 'conflicts'], true) && $fitments->isEmpty())
            <x-admin.empty title="Niciun rezultat" hint="Nimic nu se potrivește cu această combinație de căutare și filtre." />
        @endif
    </div>
</div>
