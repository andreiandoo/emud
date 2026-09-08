<div>
    <a href="{{ route('admin.catalog-platform.explorer') }}" class="mb-2 inline-block text-sm text-stone-500 hover:text-stone-900">← Catalog Explorer</a>

    <x-admin.page-header :title="trim($part->brand?->name.' '.$part->mpn_raw)" :subtitle="$part->name" />

    <div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-admin.stat :value="$part->category?->full_path ?? '—'" label="Categorie" />
        <x-admin.stat :value="$part->quality_score ?? '—'" label="Scor de calitate" />
        <x-admin.stat :value="$part->numbers->count()" label="Numere" />
        <x-admin.stat :value="$part->fitments->count()" label="Fitment-uri" />
        <x-admin.stat :value="$part->outgoingRelations->count() + $part->incomingRelations->count()"
                      :label="'Relații · '.$part->unresolvedRelations->where('status', 'pending')->count().' în așteptare'" />
    </div>

    <div class="space-y-8">
        <x-admin.section title="Identificatori">
            @if($part->numbers->isEmpty())
                <p class="text-sm text-stone-500">Piesa nu are niciun număr înregistrat.</p>
            @else
                <div class="grid gap-2 md:grid-cols-2">
                    @foreach($part->numbers as $number)
                        <div class="rounded-xl bg-stone-50 p-3" wire:key="number-{{ $number->id }}">
                            <div class="text-sm">
                                <span class="font-medium text-stone-900">{{ $number->scheme }}</span>
                                <span class="font-mono">{{ $number->number_raw }}</span>
                                @if($number->oeMake)<span class="text-stone-500">· {{ $number->oeMake->name }}</span>@endif
                            </div>
                            <div class="mt-1 text-xs text-stone-500">
                                sursă {{ $number->source?->code ?? '—' }} · încredere {{ $number->confidence ?? '—' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.section>

        <x-admin.section title="Compatibilitate vehicule">
            @if($part->fitments->isEmpty())
                <p class="text-sm text-stone-500">Piesa nu este legată de niciun vehicul.</p>
            @else
                <ul class="divide-y divide-stone-100">
                    @foreach($part->fitments as $fitment)
                        <li class="py-3" wire:key="part-fitment-{{ $fitment->id }}">
                            <a href="{{ route('admin.catalog-platform.vehicles.show', $fitment->configuration) }}" class="font-medium text-stone-900 hover:underline">
                                {{ $fitment->configuration?->generation?->model?->make?->name }}
                                {{ $fitment->configuration?->generation?->model?->name }}
                                {{ $fitment->configuration?->generation?->name }}
                            </a>
                            <div class="text-sm text-stone-500">
                                {{ $fitment->position }} · încredere {{ $fitment->confidence ?? '—' }} · {{ $fitment->status }}
                            </div>

                            {{-- Constraints are the difference between "fits" and "fits if"; they are
                                 shown next to the fitment rather than hidden behind it. --}}
                            @foreach($fitment->constraints as $constraint)
                                <div class="mt-1 text-xs text-amber-700">
                                    {{ $constraint->display_text ?: $constraint->constraint_type.': '.$constraint->value_text }}
                                </div>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.section>

        <x-admin.section title="Graf de relații canonice">
            <x-slot:aside>
                @if($part->unresolvedRelations->where('status', 'pending')->isNotEmpty())
                    <a href="{{ route('admin.catalog-platform.unresolved-relations', ['q' => $part->mpn_raw]) }}" class="hover:text-stone-900 hover:underline">Deschide QA relații</a>
                @endif
            </x-slot:aside>

            <p class="text-sm text-stone-500">
                Echivalențe rezolvate, referințe încrucișate și înlocuiri, cu sursa fiecărei legături.
            </p>

            @if($part->outgoingRelations->isEmpty() && $part->incomingRelations->isEmpty())
                <x-admin.empty title="Nicio relație rezolvată" hint="Referințele nerezolvate apar mai jos." />
            @else
                <div class="grid gap-3 lg:grid-cols-2">
                    @foreach($part->outgoingRelations as $relation)
                        <div class="rounded-xl bg-stone-50 p-3" wire:key="out-{{ $relation->id }}">
                            <div class="text-xs uppercase tracking-wider text-stone-400">Ieșire · {{ $relation->relation_type }}</div>
                            <a href="{{ route('admin.catalog-platform.parts.show', $relation->targetPart) }}" class="mt-1 block font-medium text-stone-900 hover:underline">
                                {{ $relation->targetPart?->brand?->name }} {{ $relation->targetPart?->mpn_raw }}
                            </a>
                            <div class="mt-1 text-xs text-stone-500">
                                {{ $relation->is_directed ? 'direcționată' : 'nedirecționată' }} · încredere {{ $relation->confidence ?? '—' }} · sursă {{ $relation->source?->code ?? '—' }}
                            </div>
                        </div>
                    @endforeach

                    @foreach($part->incomingRelations as $relation)
                        <div class="rounded-xl bg-stone-50 p-3" wire:key="in-{{ $relation->id }}">
                            <div class="text-xs uppercase tracking-wider text-stone-400">Intrare · {{ $relation->relation_type }}</div>
                            <a href="{{ route('admin.catalog-platform.parts.show', $relation->sourcePart) }}" class="mt-1 block font-medium text-stone-900 hover:underline">
                                {{ $relation->sourcePart?->brand?->name }} {{ $relation->sourcePart?->mpn_raw }}
                            </a>
                            <div class="mt-1 text-xs text-stone-500">
                                {{ $relation->is_directed ? 'direcționată' : 'nedirecționată' }} · încredere {{ $relation->confidence ?? '—' }} · sursă {{ $relation->source?->code ?? '—' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($part->unresolvedRelations->isNotEmpty())
                <div class="border-t border-stone-100 pt-4">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-stone-500">Referințe nerezolvate din surse</h3>

                    <div class="mt-3 space-y-2">
                        @foreach($part->unresolvedRelations as $pending)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-stone-50 p-3 text-sm" wire:key="pending-{{ $pending->id }}">
                                <div>
                                    <strong class="text-stone-900">{{ $pending->relation_type }}</strong> →
                                    {{ $pending->target_brand_raw }} {{ $pending->target_number_raw }}
                                    <span class="text-stone-500">({{ $pending->target_scheme }})</span>
                                </div>
                                <div class="text-xs text-stone-500">
                                    {{ $pending->status }} · {{ $pending->resolution_attempts ?? 0 }} încercări · sursă {{ $pending->source?->code ?? '—' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-admin.section>
    </div>
</div>
