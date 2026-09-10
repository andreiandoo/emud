<div>
    <a href="{{ route('admin.workshops.index') }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">← Registru național</a>

    <x-admin.page-header :title="$workshop->name" :subtitle="collect([$workshop->locality, $workshop->county])->filter()->implode(', ') ?: 'Fără localitate'">
        <x-slot:actions>
            @foreach(['is_rar_authorized' => 'Autorizat RAR', 'is_itp' => 'ITP', 'is_gpl_gnc' => 'GPL/GNC', 'is_tlv' => 'Tahografe', 'is_modification_authorized' => 'Modificări B4', 'is_dismantling' => 'Dezmembrări', 'is_mobile' => 'Atelier mobil'] as $flag => $label)
                @if($workshop->{$flag})
                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-700">{{ $label }}</span>
                @endif
            @endforeach
            @unless($workshop->is_active)
                <x-admin.status label="Inactiv" tone="warning" />
            @endunless
        </x-slot:actions>
    </x-admin.page-header>

    @if($workshop->mergedInto)
        <p class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Duplicat, unit în <a class="underline" href="{{ route('admin.workshops.show', $workshop->mergedInto) }}">{{ $workshop->mergedInto->name }}</a>.
        </p>
    @endif

    <div class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :value="$workshop->confidence_score ?? '—'" label="Încredere (0–100)" />
        <x-admin.stat :value="$workshop->offroad_score ?? '—'" label="Relevanță off-road (0–100)" />
        <x-admin.stat :value="$workshop->coordinates_source ? $workshop->coordinates_source.' · '.$workshop->coordinates_confidence : 'fără'" label="Coordonate (sursă · încredere)" />
        <x-admin.stat :value="$workshop->last_verified_at?->timezone('Europe/Bucharest')->format('d.m.Y') ?? '—'" label="Verificat ultima dată la sursă" />
    </div>

    <div class="mb-8 grid gap-6 xl:grid-cols-2">
        <x-admin.panel title="Firmă" :subtitle="$workshop->company?->onrc_verified_at ? 'Identitate confirmată în ONRC' : 'Identitate din RAR, încă neconfirmată în ONRC'">
            @if($workshop->company)
                <x-admin.definition cols="wide" :rows="[
                    'Denumire' => $workshop->company->legal_name,
                    'CUI' => $workshop->company->cui,
                    'Nr. Reg. Com.' => $workshop->company->registration_number,
                    'Stare ONRC' => $workshop->company->status,
                    'Sediu social' => $workshop->company->registered_address,
                    'Plătitor TVA' => $workshop->company->is_vat_payer === null ? null : ($workshop->company->is_vat_payer ? 'da' : 'nu'),
                    'Ateliere ale firmei' => $companyWorkshops,
                ]" />
                @if($workshop->company->caen_codes)
                    <p class="text-xs text-stone-500">CAEN autorizat: {{ collect($workshop->company->caen_codes)->map(fn ($caen) => $caen['code'].($caen['version'] !== '' ? ' (v'.$caen['version'].')' : ''))->implode(', ') }}</p>
                @endif
            @else
                <p class="text-sm text-stone-500">Nicio firmă legată: atelierul este cunoscut doar din OpenStreetMap sau de pe site.</p>
            @endif
        </x-admin.panel>

        <x-admin.panel title="Locație" subtitle="Punctul de lucru, nu sediul social.">
            <x-admin.definition cols="wide" :rows="[
                'Adresă' => $workshop->address,
                'Localitate' => $workshop->locality,
                'Județ' => $workshop->county ? $workshop->county.' ('.$workshop->county_code.')' : null,
                'Cod poștal' => $workshop->postal_code,
                'Coordonate' => $workshop->hasCoordinates() ? number_format($workshop->latitude, 6, '.', '').', '.number_format($workshop->longitude, 6, '.', '') : null,
                'Stare geocodare' => $workshop->geocode_status,
                'Posturi de lucru' => $workshop->workstations,
                'Angajați' => $workshop->employees,
            ]" />
            @if($workshop->hasCoordinates())
                <a class="text-sm text-stone-600 underline" target="_blank" rel="noopener"
                   href="https://www.openstreetmap.org/?mlat={{ $workshop->latitude }}&mlon={{ $workshop->longitude }}#map=18/{{ $workshop->latitude }}/{{ $workshop->longitude }}">Vezi pe hartă</a>
            @endif
        </x-admin.panel>
    </div>

    <x-admin.section title="Contacte" class="mb-8">
        <x-slot:aside>{{ $workshop->contacts->count() }}</x-slot:aside>

        @if($workshop->contacts->isEmpty())
            <p class="text-sm text-stone-500">Niciun contact încă.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr><th>Tip</th><th>Valoare</th><th>Etichetă</th><th>Sursă</th><th class="text-right">Încredere</th><th>Văzut</th></tr>
                    </thead>
                    <tbody>
                        @foreach($workshop->contacts->sortBy([['is_primary', 'desc'], ['type', 'asc'], ['confidence_score', 'desc']]) as $contact)
                            <tr wire:key="contact-{{ $contact->id }}">
                                <td class="whitespace-nowrap">
                                    {{ $contact->type }}
                                    @if($contact->is_primary)<span class="text-xs text-emerald-700">· principal</span>@endif
                                </td>
                                <td class="font-medium">
                                    @if(in_array($contact->type, ['website', 'facebook', 'instagram'], true))
                                        <a href="{{ $contact->value }}" target="_blank" rel="noopener nofollow" class="underline">{{ $contact->value }}</a>
                                    @elseif($contact->type === 'email')
                                        <a href="mailto:{{ $contact->value }}" class="underline">{{ $contact->value }}</a>
                                    @else
                                        {{ $contact->value }}
                                    @endif
                                </td>
                                <td class="text-stone-600">{{ $contact->label ?? '—' }}</td>
                                <td class="text-stone-600">
                                    {{ $contact->dataSource?->key ?? 'manual' }}
                                    @if($contact->source_record_id)
                                        · <a href="{{ route('admin.workshops.records.show', $contact->source_record_id) }}" class="underline">înregistrare</a>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $contact->confidence_score }}</td>
                                <td class="whitespace-nowrap text-stone-500">{{ $contact->first_seen_at?->format('d.m.Y') }} – {{ $contact->last_seen_at?->format('d.m.Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.section>

    <div class="mb-8 grid gap-6 xl:grid-cols-2">
        <x-admin.section title="Capabilități">
            @forelse($workshop->capabilities->sortBy('capability') as $capability)
                <div class="rounded-xl bg-stone-50 p-3 text-sm" wire:key="capability-{{ $capability->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-stone-900">{{ $capabilityLabels[$capability->capability] ?? $capability->capability }}</span>
                        <span class="tabular-nums text-stone-600">
                            {{ $capability->value === null ? 'nedeterminat' : ($capability->value ? 'da' : 'nu') }} · {{ $capability->score }} · {{ $capability->basis }}
                        </span>
                    </div>
                    <ul class="mt-1 space-y-0.5 text-xs text-stone-500">
                        @foreach(array_slice((array) $capability->evidence, 0, 6) as $item)
                            <li>{{ trim(($item['code'] ?? '').' '.($item['text'] ?? ($item['service'] ?? ''))) ?: json_encode($item, JSON_UNESCAPED_UNICODE) }}</li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="text-sm text-stone-500">Nicio capabilitate determinată.</p>
            @endforelse
        </x-admin.section>

        <x-admin.section title="Servicii">
            @forelse($workshop->services->groupBy(fn ($service) => $service->evidence_type->value) as $type => $services)
                <div class="space-y-1.5">
                    <h3 class="text-sm font-semibold text-stone-800">{{ \App\Enums\WorkshopEvidenceType::from($type)->label() }}</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($services->sortByDesc('confidence_score') as $service)
                            <span class="rounded bg-stone-100 px-2 py-1 text-xs text-stone-700"
                                  title="{{ collect($service->evidence)->map(fn ($item) => $item['code'] ?? $item['snippet'] ?? $item['osm'] ?? '')->filter()->take(4)->implode(' · ') }}">
                                {{ \App\Workshops\Classification\ServiceTaxonomy::name((string) $service->serviceType?->key) }}
                                <span class="text-stone-400">{{ $service->confidence_score }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Niciun serviciu cunoscut.</p>
            @endforelse
        </x-admin.section>
    </div>

    <x-admin.section title="Autorizații RAR" class="mb-8">
        <x-slot:aside>{{ $workshop->authorizations->where('is_current', true)->count() }} în vigoare</x-slot:aside>

        @forelse($workshop->authorizations as $authorization)
            <details class="rounded-xl border border-stone-200 bg-white p-4" @if($authorization->is_current) open @endif wire:key="authorization-{{ $authorization->id }}">
                <summary class="cursor-pointer text-sm">
                    <span class="font-semibold text-stone-900">{{ $systemLabels[$authorization->system] ?? $authorization->system }}</span>
                    · nr. {{ $authorization->authorization_number ?? '—' }}
                    · {{ $authorization->exit_number ?? 'fără nr. de ieșire' }}
                    @if($authorization->station_code) · stația {{ $authorization->station_code }} @endif
                    @if($authorization->audit_file_number) · dosar {{ $authorization->audit_file_number }} @endif
                    @if($authorization->authorization_class) · {{ $authorization->authorization_class }} @endif
                    · valabilă {{ $authorization->valid_from?->format('d.m.Y') ?? '?' }} – {{ $authorization->valid_until?->format('d.m.Y') ?? '?' }}
                    @unless($authorization->is_current) <span class="text-amber-700">· nu mai e listată de RAR</span> @endunless
                </summary>

                <div class="mt-3 text-xs text-stone-500">
                    <a href="{{ route('admin.workshops.records.show', $authorization->source_record_id) }}" class="underline">Înregistrarea brută RAR</a>
                    @if(! empty($authorization->raw_data['brands']))
                        · service autorizat pentru: {{ implode(', ', $authorization->raw_data['brands']) }}
                    @endif
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr><th>Cod</th><th>Descriere RAR</th><th>Categorii</th><th>Limitări</th><th>Restricții</th><th>Observații</th></tr>
                        </thead>
                        <tbody>
                            @foreach($authorization->activities as $activity)
                                <tr wire:key="activity-{{ $activity->id }}">
                                    <td class="whitespace-nowrap font-mono" style="padding-left: {{ 0.5 + $activity->depth * 0.75 }}rem">{{ $activity->display_code }}</td>
                                    <td>{{ $activity->description ?? '—' }}</td>
                                    <td class="text-stone-600">{{ implode(', ', (array) $activity->vehicle_categories) }}</td>
                                    <td class="text-stone-600">{{ implode('; ', (array) $activity->limitations) }}</td>
                                    <td class="text-stone-600">{{ collect((array) $activity->restrictions)->pluck('text')->implode('; ') }}</td>
                                    <td class="text-stone-600">{{ implode('; ', (array) $activity->observations) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @empty
            <p class="text-sm text-stone-500">Nicio autorizație RAR.</p>
        @endforelse
    </x-admin.section>

    <div class="mb-8 grid gap-6 xl:grid-cols-2">
        <x-admin.section title="Surse">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th>Sursă</th><th>Înregistrare</th><th>Potrivire</th><th class="text-right">Încredere</th></tr></thead>
                    <tbody>
                        @foreach($workshop->sourceLinks as $link)
                            <tr wire:key="link-{{ $link->id }}">
                                <td>{{ $link->dataSource?->name ?? '—' }}</td>
                                <td class="font-mono text-xs">
                                    <a href="{{ route('admin.workshops.records.show', $link->source_record_id) }}" class="underline">{{ \Illuminate\Support\Str::limit($link->external_id, 40) }}</a>
                                    @if($link->sourceRecord && ! $link->sourceRecord->is_current) <span class="text-amber-700">· retrasă</span> @endif
                                </td>
                                <td class="text-stone-600">{{ $link->match_type }}</td>
                                <td class="text-right tabular-nums">{{ $link->match_confidence }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.section>

        <x-admin.section title="Site-uri candidate">
            @forelse($workshop->websiteCandidates as $site)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-stone-50 p-3 text-sm" wire:key="site-{{ $site->id }}">
                    <a href="{{ $site->url }}" target="_blank" rel="noopener nofollow" class="truncate underline">{{ $site->domain }}</a>
                    <span class="whitespace-nowrap text-stone-600">{{ $site->discovered_via }} · {{ $site->confidence }} · {{ $site->validation_status }}</span>
                </div>
            @empty
                <p class="text-sm text-stone-500">Niciun site găsit încă (php artisan workshops:web:discover).</p>
            @endforelse
        </x-admin.section>
    </div>

    @if($candidates->isNotEmpty() || $merged->isNotEmpty())
        <x-admin.section title="Posibile duplicate">
            @foreach($candidates as $pair)
                @php($other = $pair->workshop_a_id === $workshop->id ? $pair->workshopB : $pair->workshopA)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-stone-50 p-3 text-sm" wire:key="pair-{{ $pair->id }}">
                    <a href="{{ route('admin.workshops.show', $other) }}" class="underline">{{ $other?->name }} ({{ $other?->locality }})</a>
                    <span class="text-stone-600">scor {{ $pair->score }} · {{ $pair->status->value }}</span>
                </div>
            @endforeach
            @foreach($merged as $duplicate)
                <div class="rounded-xl bg-stone-50 p-3 text-sm text-stone-600" wire:key="merged-{{ $duplicate->id }}">
                    Unit aici: <a href="{{ route('admin.workshops.show', $duplicate) }}" class="underline">{{ $duplicate->name }}</a> ({{ $duplicate->locality }})
                </div>
            @endforeach
        </x-admin.section>
    @endif
</div>
