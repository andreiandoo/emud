<div>
    <x-admin.page-header title="Registru național de ateliere" subtitle="Ateliere din RAR, ONRC, OpenStreetMap și site-urile proprii. Fiecare valoare își păstrează sursa.">
        <x-slot:actions>
            <a href="{{ route('admin.workshops.review') }}" class="btn-secondary">De verificat</a>
            <a href="{{ route('admin.workshops.sources') }}" class="btn-secondary">Surse & importuri</a>
            <button type="button" wire:click="export" class="btn-primary">Exportă CSV</button>
        </x-slot:actions>
    </x-admin.page-header>

    <label class="relative mb-2 block max-w-3xl">
        <span class="sr-only">Caută ateliere</span>
        <x-admin.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
        <input wire:model.live.debounce.400ms="q" class="pl-9" placeholder="„service cutii automate Brașov”, „ITP 4x4 Iași”, un nume, un CUI sau un telefon">
    </label>

    @if($understood !== [])
        <p class="mb-3 text-sm text-stone-500">Am înțeles: <span class="text-stone-800">{{ implode(' · ', $understood) }}</span></p>
    @endif

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
        <select wire:model.live="county" aria-label="Județ">
            <option value="">Toate județele</option>
            @foreach($counties as $code => $county)
                <option value="{{ $code }}">{{ $county[0] }}</option>
            @endforeach
        </select>

        <input wire:model.live.debounce.400ms="city" placeholder="Localitate" aria-label="Localitate">

        <select wire:model.live="service" aria-label="Serviciu">
            <option value="">Orice serviciu</option>
            @foreach($services as $key => $definition)
                <option value="{{ $key }}">{{ $definition[1] ? '— ' : '' }}{{ $definition[0] }}</option>
            @endforeach
        </select>

        <select wire:model.live="evidence" aria-label="Tip de dovadă">
            <option value="">Orice dovadă</option>
            <option value="rar_authorization">Autorizat RAR</option>
            <option value="website">Declarat pe site</option>
            <option value="osm">Etichetat OSM</option>
            <option value="manual">Introdus manual</option>
        </select>

        <select wire:model.live="capability" aria-label="Capabilitate">
            <option value="">Orice capabilitate</option>
            @foreach($capabilities as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        <input wire:model.live.debounce.400ms="code" placeholder="Cod RAR, ex. A1.2.1.3" aria-label="Cod activitate RAR">

        <select wire:model.live="rar" aria-label="Autorizare RAR">
            <option value="">Autorizat RAR sau nu</option>
            <option value="1">Autorizat RAR service</option>
            <option value="0">Fără autorizație service</option>
        </select>

        <select wire:model.live="itp" aria-label="ITP">
            <option value="">Cu sau fără ITP</option>
            <option value="1">Stație ITP</option>
        </select>

        <select wire:model.live="gpl" aria-label="GPL/GNC">
            <option value="">Cu sau fără GPL/GNC</option>
            <option value="1">Autorizat GPL/GNC</option>
        </select>

        <select wire:model.live="contact" aria-label="Contact">
            <option value="">Orice contact</option>
            <option value="phone">Cu telefon</option>
            <option value="email">Cu email</option>
            <option value="website">Cu site</option>
            <option value="none">Fără telefon și email</option>
        </select>

        <select wire:model.live="coordinates" aria-label="Coordonate">
            <option value="">Cu sau fără coordonate</option>
            <option value="1">Cu coordonate</option>
            <option value="0">Fără coordonate</option>
        </select>

        <select wire:model.live="source" aria-label="Sursă">
            <option value="">Orice sursă</option>
            @foreach($sources as $key => $name)
                <option value="{{ $key }}">{{ $name }}</option>
            @endforeach
        </select>

        <select wire:model.live="confidence" aria-label="Încredere minimă">
            <option value="">Orice încredere</option>
            <option value="80">Încredere ≥ 80</option>
            <option value="60">Încredere ≥ 60</option>
            <option value="40">Încredere ≥ 40</option>
        </select>

        <select wire:model.live="state" aria-label="Stare">
            <option value="active">Active</option>
            <option value="inactive">Inactive sau unite</option>
            <option value="all">Toate</option>
        </select>

        <select wire:model.live="sort" aria-label="Ordonare">
            <option value="name">După nume</option>
            <option value="confidence">După încredere</option>
            <option value="offroad">După relevanța off-road</option>
            <option value="recent">Actualizate recent</option>
        </select>

        <button type="button" wire:click="resetFilters" class="btn-secondary">Resetează filtrele</button>
    </div>

    <p class="mb-2 text-sm text-stone-500">{{ number_format($workshops->total(), 0, ',', '.') }} ateliere</p>

    @if($workshops->isEmpty())
        <x-admin.empty title="Niciun atelier" hint="Nimic nu se potrivește cu filtrele curente sau registrul nu a fost încă importat (php artisan workshops:rar:import)." />
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr>
                        <th>Atelier</th>
                        <th>Localitate</th>
                        <th>Autorizații RAR</th>
                        <th>Capabilități</th>
                        <th>Contact</th>
                        <th class="text-right">Încredere</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workshops as $workshop)
                        <tr wire:key="workshop-{{ $workshop->id }}">
                            <td>
                                <a href="{{ route('admin.workshops.show', $workshop) }}" class="font-medium text-stone-900 hover:underline">{{ $workshop->name }}</a>
                                <div class="text-xs text-stone-500">
                                    {{ $workshop->company?->cui ? 'CUI '.$workshop->company->cui : 'fără CUI' }}
                                    @if($workshop->address) · {{ \Illuminate\Support\Str::limit($workshop->address, 70) }} @endif
                                </div>
                            </td>
                            <td class="text-stone-600">
                                {{ $workshop->locality ?? '—' }}
                                <div class="text-xs text-stone-500">{{ $workshop->county }}</div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach(['is_rar_authorized' => 'Service', 'is_itp' => 'ITP', 'is_gpl_gnc' => 'GPL', 'is_tlv' => 'TLV', 'is_modification_authorized' => 'B4'] as $flag => $label)
                                        @if($workshop->{$flag})
                                            <span class="rounded bg-stone-100 px-1.5 py-0.5 text-xs font-medium text-stone-700">{{ $label }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-xs text-stone-600">
                                @php($on = $workshop->capabilities->where('value', true)->pluck('capability'))
                                {{ $on->map(fn ($capability) => $capabilities[$capability] ?? $capability)->implode(' · ') ?: '—' }}
                                @if($workshop->offroad_score)
                                    <div class="text-stone-500">off-road {{ $workshop->offroad_score }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-stone-600">
                                @php($types = $workshop->contacts->pluck('type'))
                                {{ $types->intersect(['phone', 'mobile'])->isNotEmpty() ? 'tel' : '' }}
                                {{ $types->contains('email') ? '· email' : '' }}
                                {{ $types->contains('website') ? '· site' : '' }}
                            </td>
                            <td class="text-right tabular-nums">{{ $workshop->confidence_score ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $workshops->links() }}</div>
    @endif
</div>
