<div>
    <x-admin.page-header title="Conflicte de catalog"
                         subtitle="Afirmații contradictorii și decizii de canonicalizare, revizuite fără a șterge dovada din sursă." />

    @if(session('status'))
        <p class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</p>
    @endif

    {{-- The three counts double as the status filter: they are the first thing looked at and the
         first thing clicked, so making them two separate controls only adds a step. --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        @foreach(['open' => 'Deschise', 'resolved' => 'Rezolvate', 'dismissed' => 'Respinse'] as $key => $label)
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
            <span class="field-label">Caută entitate, relație sau detalii</span>
            <input wire:model.live.debounce.300ms="q" placeholder="technical_identity_mismatch, fitment, part number…">
        </label>

        <label class="block">
            <span class="field-label">Tip entitate</span>
            <select wire:model.live="type">
                <option value="">Toate</option>
                @foreach($types as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>

        <label class="block">
            <span class="field-label">Severitate</span>
            <select wire:model.live="severity">
                <option value="">Toate</option>
                @foreach(['error', 'warning', 'info'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </select>
        </label>
    </div>

    <div class="space-y-4">
        @forelse($conflicts as $conflict)
            <article class="card-padded" wire:key="conflict-{{ $conflict->id }}">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-stone-900">{{ $conflict->entity_type }} #{{ $conflict->entity_id }}</strong>
                            <span class="rounded-full bg-stone-100 px-2.5 py-0.5 font-mono text-xs text-stone-600">{{ $conflict->field_or_relation }}</span>
                            <span class="{{ match ($conflict->severity) {
                                'error' => 'pill-danger',
                                'warning' => 'pill-warning',
                                default => 'pill-neutral',
                            } }}">{{ $conflict->severity }}</span>
                            <span class="text-xs text-stone-500">{{ $conflict->status }}</span>
                        </div>

                        <pre class="mt-3 max-h-80 overflow-auto rounded-lg bg-stone-50 p-3 text-xs text-stone-700">{{ json_encode($conflict->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

                        @if($conflict->resolution)
                            <div class="mt-3 rounded-lg border border-stone-200 p-3 text-xs text-stone-600">
                                <strong class="text-stone-800">Decizia înregistrată</strong>
                                <pre class="mt-2 overflow-auto">{{ json_encode($conflict->resolution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif
                    </div>

                    <div class="w-full space-y-2 sm:w-72">
                        <label class="block">
                            <span class="field-label">Notă de revizuire</span>
                            <textarea wire:model="notes.{{ $conflict->id }}" rows="3" class="text-xs" placeholder="Motivul deciziei"></textarea>
                        </label>

                        @if($conflict->status === 'open')
                            <button type="button" wire:click="resolve({{ $conflict->id }}, 'keep_canonical')" class="btn-primary w-full">Păstrează valoarea canonică</button>
                            <button type="button" wire:click="resolve({{ $conflict->id }}, 'accept_source')" class="btn-secondary w-full">Acceptă decizia sursei</button>
                            <button type="button" wire:click="resolve({{ $conflict->id }}, 'not_a_conflict')" class="btn-ghost w-full">Nu este un conflict</button>
                            <button type="button" wire:click="dismiss({{ $conflict->id }})" wire:confirm="Respingi conflictul?" class="btn-danger w-full">Respinge</button>
                        @else
                            <button type="button" wire:click="reopen({{ $conflict->id }})" class="btn-secondary w-full">Redeschide conflictul</button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <x-admin.empty title="Niciun conflict" hint="Nimic nu se potrivește cu filtrele curente." />
        @endforelse
    </div>

    <div class="mt-4">{{ $conflicts->links() }}</div>
</div>
