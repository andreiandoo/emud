<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Catalog\Relations\CatalogPartRelationResolver;
use App\Models\CatalogPart;
use App\Models\CatalogSource;
use App\Models\CatalogUnresolvedPartRelation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class PartRelationsQueue extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $relationType = '';

    #[Url]
    public string $sourceId = '';

    #[Url]
    public string $search = '';

    /** @var array<int, string> */
    public array $manualTargets = [];

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRelationType(): void
    {
        $this->resetPage();
    }

    public function updatedSourceId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function retry(int $pendingId, CatalogPartRelationResolver $resolver): void
    {
        $resolved = $resolver->resolveOne($pendingId);
        session()->flash('status', $resolved ? 'Relation resolved.' : 'Target is still unavailable.');
    }

    public function ignore(int $pendingId): void
    {
        $pending = CatalogUnresolvedPartRelation::query()->findOrFail($pendingId);
        if ($pending->status !== 'pending') {
            return;
        }

        $metadata = $pending->metadata ?? [];
        $metadata['qa'] = [
            'action' => 'ignored',
            'user_id' => auth()->id(),
            'at' => now()->toIso8601String(),
        ];

        $pending->update(['status' => 'ignored', 'metadata' => $metadata]);
    }

    public function reopen(int $pendingId): void
    {
        $pending = CatalogUnresolvedPartRelation::query()->findOrFail($pendingId);
        if ($pending->status !== 'ignored') {
            return;
        }

        $metadata = $pending->metadata ?? [];
        $metadata['qa'] = [
            'action' => 'reopened',
            'user_id' => auth()->id(),
            'at' => now()->toIso8601String(),
        ];

        $pending->update([
            'status' => 'pending',
            'resolved_target_part_id' => null,
            'resolved_at' => null,
            'metadata' => $metadata,
        ]);
    }

    public function manualResolve(int $pendingId, CatalogPartRelationResolver $resolver): void
    {
        $targetValue = trim((string) ($this->manualTargets[$pendingId] ?? ''));
        if ($targetValue === '') {
            $this->addError("manualTargets.{$pendingId}", 'Enter a catalog part ID or public ID.');

            return;
        }

        $target = CatalogPart::query()
            ->when(
                ctype_digit($targetValue),
                fn ($query) => $query->whereKey((int) $targetValue),
                fn ($query) => $query->where('public_id', str_replace('prt_', '', $targetValue)),
            )
            ->first();

        if (! $target) {
            $this->addError("manualTargets.{$pendingId}", 'Target catalog part was not found.');

            return;
        }

        $pending = CatalogUnresolvedPartRelation::query()->findOrFail($pendingId);
        $resolved = $resolver->resolveTo($pending, $target);
        if (! $resolved) {
            $this->addError("manualTargets.{$pendingId}", 'The relation could not be resolved to that target.');

            return;
        }

        $pending->refresh();
        $metadata = $pending->metadata ?? [];
        $metadata['qa'] = [
            'action' => 'manual_resolve',
            'user_id' => auth()->id(),
            'target_part_id' => $target->id,
            'at' => now()->toIso8601String(),
        ];
        $pending->update(['metadata' => $metadata]);
        unset($this->manualTargets[$pendingId]);
        session()->flash('status', 'Relation manually resolved.');
    }

    public function render()
    {
        $query = CatalogUnresolvedPartRelation::query()
            ->with(['sourcePart.brand', 'resolvedTargetPart.brand', 'source', 'sourceRecord'])
            ->when($this->status, fn ($builder) => $builder->where('status', $this->status))
            ->when($this->relationType, fn ($builder) => $builder->where('relation_type', $this->relationType))
            ->when($this->sourceId !== '', fn ($builder) => $builder->where('catalog_source_id', (int) $this->sourceId))
            ->when(trim($this->search) !== '', function ($builder): void {
                $needle = '%'.trim($this->search).'%';
                $builder->where(function ($nested) use ($needle): void {
                    $nested->where('target_number_raw', 'ilike', $needle)
                        ->orWhere('target_brand_raw', 'ilike', $needle)
                        ->orWhereHas('sourcePart', fn ($part) => $part->where('mpn_raw', 'ilike', $needle))
                        ->orWhereHas('sourcePart.brand', fn ($brand) => $brand->where('name', 'ilike', $needle));
                });
            })
            ->orderByDesc('confidence')
            ->orderBy('id');

        return view('livewire.admin.catalog-platform.part-relations-queue', [
            'relations' => $query->paginate(50),
            'sources' => CatalogSource::query()->whereHas('records')->orderBy('name')->get(['id', 'name', 'code']),
            'relationTypes' => CatalogUnresolvedPartRelation::query()->distinct()->orderBy('relation_type')->pluck('relation_type'),
            'counts' => CatalogUnresolvedPartRelation::query()
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }
}
