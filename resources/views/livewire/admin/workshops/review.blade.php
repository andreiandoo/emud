<div>
    <x-admin.page-header title="Ateliere de verificat" subtitle="Ce nu a putut fi decis automat. Deciziile de aici nu sunt refăcute de importurile următoare.">
        <x-slot:actions>
            <a href="{{ route('admin.workshops.index') }}" class="btn-secondary">Registru național</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
    @endif

    <x-admin.tabs :tabs="['duplicates' => 'Posibile duplicate', 'records' => 'Potriviri OSM / ONRC']" :current="$tab" :counts="$counts" field="tab" />

    @if($tab === 'duplicates')
        @if($pairs->isEmpty())
            <x-admin.empty title="Niciun duplicat de verificat" hint="php artisan workshops:deduplicate caută perechile noi." />
        @else
            <div class="space-y-3">
                @foreach($pairs as $pair)
                    <div class="rounded-xl border border-stone-200 bg-white p-4" wire:key="pair-{{ $pair->id }}">
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach([$pair->workshopA, $pair->workshopB] as $side)
                                <div class="text-sm">
                                    @if($side)
                                        <a href="{{ route('admin.workshops.show', $side) }}" class="font-medium text-stone-900 hover:underline">{{ $side->name }}</a>
                                        <div class="text-stone-600">{{ $side->address }}</div>
                                        <div class="text-xs text-stone-500">
                                            {{ $side->locality }} · {{ $side->company?->cui ? 'CUI '.$side->company->cui : 'fără CUI' }}
                                            @if($side->is_rar_authorized) · autorizat RAR @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-stone-500">
                            <span>
                                scor {{ $pair->score }}
                                @foreach((array) $pair->evidence as $key => $value)
                                    · {{ $key }}: {{ is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'da' : 'nu') : $value) }}
                                @endforeach
                            </span>
                            <span class="flex gap-2">
                                <button type="button" class="btn-secondary" wire:click="keepApart({{ $pair->id }})">Sunt ateliere diferite</button>
                                <button type="button" class="btn-primary" wire:click="merge({{ $pair->id }})" wire:confirm="Unești cele două ateliere? Sursele, contactele și autorizațiile trec la cel păstrat.">Unește</button>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $pairs->links() }}</div>
        @endif
    @else
        @if($records->isEmpty())
            <x-admin.empty title="Nicio potrivire de verificat" hint="Punctele OSM și firmele ONRC ambigue apar aici după import." />
        @else
            <div class="space-y-3">
                @foreach($records as $match)
                    @php($payload = (array) $match->sourceRecord?->payload)
                    <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm" wire:key="match-{{ $match->id }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <span class="font-medium text-stone-900">{{ $payload['tags']['name'] ?? $payload['DENUMIRE'] ?? $match->sourceRecord?->external_id }}</span>
                                <span class="text-xs text-stone-500">· {{ $match->sourceRecord?->dataSource?->name }} · {{ $match->status->value }} · scor {{ $match->score ?? '—' }}</span>
                            </div>
                            <a href="{{ route('admin.workshops.records.show', $match->source_record_id) }}" class="text-xs underline">înregistrarea brută</a>
                        </div>

                        <div class="mt-3 space-y-1.5">
                            @foreach(array_slice((array) $match->candidates, 0, 5) as $candidate)
                                @php($targetId = $candidate['workshop_id'] ?? $candidate['company_id'] ?? null)
                                @if($targetId)
                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-stone-50 px-3 py-2">
                                        <span>
                                            @if($match->target_type === 'workshop')
                                                <a href="{{ route('admin.workshops.show', $targetId) }}" class="underline">{{ $workshopNames[$targetId] ?? '#'.$targetId }}</a>
                                            @else
                                                {{ $companyNames[$targetId] ?? '#'.$targetId }}
                                            @endif
                                            <span class="text-xs text-stone-500">scor {{ $candidate['score'] ?? '—' }}</span>
                                        </span>
                                        <button type="button" class="btn-secondary" wire:click="linkRecord({{ $match->id }}, {{ $targetId }})">Este acesta</button>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="mt-3 text-right">
                            <button type="button" class="btn-secondary" wire:click="rejectRecord({{ $match->id }})">Niciunul dintre ei</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    @endif
</div>
