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
                @foreach($reviewItems as $item)
                    @php($match = $item['match'])
                    @php($subject = $item['subject'])
                    <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm" wire:key="match-{{ $match->id }}">
                        {{-- What the point (or the ONRC company) says about itself: the thing to compare against. --}}
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs text-stone-500">
                                    {{ $match->sourceRecord?->dataSource?->name }} ·
                                    {{ $match->status === \App\Enums\WorkshopRecordMatchStatus::Ambiguous ? 'mai mulți candidați la fel de potriviți' : 'potrivire probabilă, neconfirmată' }}
                                </div>
                                <div class="mt-0.5 font-medium text-stone-900">{{ $subject['name'] ?? $match->sourceRecord?->external_id }}</div>
                                <div class="text-stone-700">{{ $subject['address'] ?? ($match->target_type === 'workshop' ? 'Punctul nu are adresă în OpenStreetMap' : 'Fără adresă') }}</div>
                                <div class="text-xs text-stone-500">
                                    @if($subject['phones'])tel. {{ implode(', ', $subject['phones']) }}@endif
                                    @foreach($subject['websites'] as $website) · {{ $website }}@endforeach
                                    @foreach($subject['details'] as $detail) · {{ $detail }}@endforeach
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-3 text-xs">
                                @if($subject['url'])<a href="{{ $subject['url'] }}" target="_blank" rel="noopener" class="underline">vezi în OpenStreetMap</a>@endif
                                @if($subject['map'])<a href="{{ $subject['map'] }}" target="_blank" rel="noopener" class="underline">punctul pe hartă</a>@endif
                                <a href="{{ route('admin.workshops.records.show', $match->source_record_id) }}" class="underline">înregistrarea brută</a>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach($item['candidates'] as $candidate)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg px-3 py-2 {{ $candidate['suggested'] ? 'bg-emerald-50' : 'bg-stone-50' }}" wire:key="match-{{ $match->id }}-{{ $candidate['id'] }}">
                                    <div class="min-w-0">
                                        <div>
                                            @if($candidate['url'])
                                                <a href="{{ $candidate['url'] }}" class="font-medium text-stone-900 underline">{{ $candidate['name'] ?? '#'.$candidate['id'] }}</a>
                                            @else
                                                <span class="font-medium text-stone-900">{{ $candidate['name'] ?? '#'.$candidate['id'] }}</span>
                                            @endif
                                            @if($candidate['suggested'])<span class="text-xs text-emerald-800">· sugestia sistemului</span>@endif
                                        </div>
                                        <div class="text-stone-700">{{ $candidate['address'] ?? 'Fără adresă' }}</div>
                                        <div class="text-xs text-stone-500">
                                            {{ $candidate['place'] }}
                                            · {{ $candidate['cui'] ? 'CUI '.$candidate['cui'] : 'fără CUI' }}
                                            @if($candidate['phones']) · tel. {{ implode(', ', $candidate['phones']) }}@endif
                                            @if($candidate['distance'] !== null)
                                                · <span class="{{ $candidate['approximate'] ? '' : 'font-medium text-stone-700' }}">la {{ $candidate['distance'] < 1000 ? $candidate['distance'].' m' : number_format($candidate['distance'] / 1000, 1, ',', '').' km' }} de punctul OSM</span>
                                                @if($candidate['approximate'])(poziția atelierului e doar la nivel de oraș)@endif
                                            @endif
                                            @foreach($candidate['signals'] as $signal) · {{ $signal }}@endforeach
                                            @if($candidate['map']) · <a href="{{ $candidate['map'] }}" target="_blank" rel="noopener" class="underline">pe hartă</a>@endif
                                        </div>
                                    </div>
                                    <button type="button" class="btn-secondary" wire:click="linkRecord({{ $match->id }}, {{ $candidate['id'] }})">Este acesta</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-end gap-3">
                            <span class="text-xs text-stone-500">
                                {{ $match->target_type === 'workshop'
                                    ? 'Dacă niciunul nu e la locul punctului, punctul OSM devine un atelier separat.'
                                    : 'Firma din ONRC rămâne nelegată de companiile de mai sus.' }}
                            </span>
                            <button type="button" class="btn-secondary" wire:click="rejectRecord({{ $match->id }})">Niciunul dintre ei</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $records->links() }}</div>
        @endif
    @endif
</div>
