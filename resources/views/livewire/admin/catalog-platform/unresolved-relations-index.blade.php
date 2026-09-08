<div>
    <x-admin.page-header title="Relații nerezolvate"
                         subtitle="Referințe încrucișate și înlocuiri care așteaptă o piesă canonică.">
        <x-slot:actions>
            <button type="button" wire:click="retryPending" class="btn-primary">Reîncearcă până la 500</button>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        @foreach(['pending' => 'În așteptare', 'resolved' => 'Rezolvate', 'rejected' => 'Respinse'] as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key }}')" @class([
                'rounded-xl p-5 text-left transition',
                'bg-stone-900 text-white' => $status === $key,
                'bg-stone-100 hover:bg-stone-200' => $status !== $key,
            ])>
                <div class="text-2xl font-semibold tracking-tight tabular-nums">{{ number_format((int) ($counts[$key] ?? 0), 0, ',', '.') }}</div>
                <div @class(['mt-1 text-sm', 'text-stone-300' => $status === $key, 'text-stone-500' => $status !== $key])>{{ $label }}</div>
            </button>
        @endforeach
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <label class="block md:col-span-2">
            <span class="field-label">Caută număr sursă/țintă, brand sau nume piesă</span>
            <input wire:model.live.debounce.300ms="q" placeholder="ex. 90915-YZZD2, MANN, OC 123">
        </label>

        <label class="block">
            <span class="field-label">Tip relație</span>
            <select wire:model.live="relationType">
                <option value="">Toate</option>
                @foreach($relationTypes as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>

        <label class="block">
            <span class="field-label">Sursă</span>
            <select wire:model.live="sourceId">
                <option value="">Toate</option>
                @foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->code }} · {{ $source->name }}</option>@endforeach
            </select>
        </label>
    </div>

    <div class="space-y-4">
        @forelse($relations as $relation)
            <article class="card-padded" wire:key="relation-{{ $relation->id }}">
                <div class="grid gap-6 xl:grid-cols-[1.2fr_1fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-stone-100 px-2.5 py-0.5 font-mono text-xs text-stone-600">{{ $relation->relation_type }}</span>
                            <span class="{{ match ($relation->status) {
                                'pending' => 'pill-warning',
                                'resolved' => 'pill-positive',
                                default => 'pill-danger',
                            } }}">{{ $relation->status }}</span>
                            <span class="text-xs text-stone-500">încredere {{ $relation->confidence ?? '—' }}%</span>
                        </div>

                        <div class="mt-4 text-xs uppercase tracking-wider text-stone-500">Piesa sursă</div>
                        <div class="font-medium text-stone-900">
                            {{ $relation->sourcePart?->brand?->name ?? 'Brand necunoscut' }}
                            {{ $relation->sourcePart?->mpn_raw ?? '#'.$relation->source_part_id }}
                        </div>
                        <div class="text-sm text-stone-600">{{ $relation->sourcePart?->name }}</div>
                        <div class="mt-2 text-xs text-stone-500">
                            {{ $relation->source?->code ?? 'Fără sursă' }}
                            @if($relation->sourceRecord) · înregistrare #{{ $relation->sourceRecord->id }}@endif
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-wider text-stone-500">Ținta cerută</div>
                        <div class="mt-1 font-mono text-base font-semibold text-stone-900">{{ $relation->target_scheme }} · {{ $relation->target_number_raw }}</div>
                        <div class="text-sm text-stone-600">{{ $relation->target_brand_raw ?: 'Brand nefurnizat' }}</div>
                        <div class="mt-3 text-xs text-stone-500">
                            {{ $relation->resolution_attempts ?? 0 }} încercări
                            @if($relation->last_resolution_attempt_at) · ultima {{ $relation->last_resolution_attempt_at->diffForHumans() }}@endif
                        </div>

                        @if($relation->resolvedTargetPart)
                            <p class="mt-3 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">
                                Legată de {{ $relation->resolvedTargetPart->brand?->name }} {{ $relation->resolvedTargetPart->mpn_raw }}
                                · prt_{{ $relation->resolvedTargetPart->public_id }}
                            </p>
                        @endif
                    </div>

                    <div class="w-full space-y-2 xl:w-64">
                        @if($relation->status === 'pending')
                            <button type="button" wire:click="retry({{ $relation->id }})" class="btn-secondary w-full">Reîncearcă automat</button>

                            <label class="block">
                                <span class="field-label">Țintă manuală</span>
                                <input wire:model="manualTargets.{{ $relation->id }}" class="font-mono text-xs" placeholder="prt_<public_id> sau ID numeric">
                                @error('manualTargets.'.$relation->id) <span class="field-error">{{ $message }}</span> @enderror
                            </label>

                            <label class="block">
                                <span class="field-label">Notă</span>
                                <textarea wire:model="reviewNotes.{{ $relation->id }}" rows="2" class="text-xs" placeholder="Opțional"></textarea>
                            </label>

                            <button type="button" wire:click="link({{ $relation->id }})" class="btn-primary w-full">Leagă ținta canonică</button>
                            <button type="button" wire:click="reject({{ $relation->id }})"
                                    wire:confirm="Scoți referința din coada de rezolvare?" class="btn-danger w-full">Respinge referința</button>
                        @elseif($relation->status === 'rejected')
                            <label class="block">
                                <span class="field-label">Motivul redeschiderii</span>
                                <textarea wire:model="reviewNotes.{{ $relation->id }}" rows="2" class="text-xs"></textarea>
                            </label>

                            <button type="button" wire:click="reopen({{ $relation->id }})" class="btn-secondary w-full">Redeschide</button>
                        @endif
                    </div>
                </div>

                @if($relation->review_note || $relation->reviewer)
                    <div class="mt-4 border-t border-stone-100 pt-3 text-xs text-stone-500">
                        Revizuită @if($relation->reviewer) de {{ $relation->reviewer->name ?? $relation->reviewer->email }} @endif
                        @if($relation->reviewed_at) pe {{ $relation->reviewed_at->format('d.m.Y H:i') }} @endif
                        @if($relation->review_note) · {{ $relation->review_note }} @endif
                    </div>
                @endif
            </article>
        @empty
            <x-admin.empty title="Nicio relație nerezolvată" hint="Nimic nu se potrivește cu filtrele curente." />
        @endforelse
    </div>

    <div class="mt-4">{{ $relations->links() }}</div>
</div>
