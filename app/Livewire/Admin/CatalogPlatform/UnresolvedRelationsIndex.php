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
class UnresolvedRelationsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $relationType = '';

    #[Url]
    public string $sourceId = '';

    #[Url]
    public string $q = '';

    public array $manualTargets = [];

    public array $reviewNotes = [];

    public function retry(int $relationId, CatalogPartRelationResolver $resolver): void
    {
        $relation = CatalogUnresolvedPartRelation::query()->findOrFail($relationId);
        $resolved = $resolver->resolveOne($relation);

        session()->flash('status', $resolved
            ? 'Reference resolved and canonical graph edge created.'
            : 'Reference was retried but no canonical target is available yet.');
    }

    public function retryPending(CatalogPartRelationResolver $resolver): void
    {
        $resolved = $resolver->resolvePending(500);
        session()->flash('status', "Retried up to 500 pending references; {$resolved} resolved.");
    }

    public function reject(int $relationId): void
    {
        $relation = CatalogUnresolvedPartRelation::query()->findOrFail($relationId);
        if ($relation->status !== 'pending') {
            return;
        }

        $relation->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $this->noteFor($relationId),
        ]);

        session()->flash('status', 'Reference rejected from the canonical relation queue.');
    }

    public function reopen(int $relationId): void
    {
        $relation = CatalogUnresolvedPartRelation::query()->findOrFail($relationId);
        if ($relation->status !== 'rejected') {
            return;
        }

        $relation->update([
            'status' => 'pending',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $this->noteFor($relationId) ?: 'Reopened for resolution review.',
        ]);

        session()->flash('status', 'Reference reopened.');
    }

    public function link(int $relationId, CatalogPartRelationResolver $resolver): void
    {
        $relation = CatalogUnresolvedPartRelation::query()->findOrFail($relationId);
        $token = trim((string) ($this->manualTargets[$relationId] ?? ''));
        if ($token === '') {
            $this->addError("manualTargets.{$relationId}", 'Enter a canonical part ID or prt_<public_id>.');

            return;
        }

        $publicId = str_starts_with($token, 'prt_') ? substr($token, 4) : $token;
        $target = is_numeric($publicId)
            ? CatalogPart::query()->find((int) $publicId)
            : CatalogPart::query()->where('public_id', $publicId)->first();

        if (! $target) {
            $this->addError("manualTargets.{$relationId}", 'Canonical target part was not found.');

            return;
        }

        if (! $resolver->resolveTo($relation, $target, auth()->id(), $this->noteFor($relationId))) {
            $this->addError("manualTargets.{$relationId}", 'The relation cannot be linked to that target.');

            return;
        }

        unset($this->manualTargets[$relationId], $this->reviewNotes[$relationId]);
        session()->flash('status', 'Reference manually linked to the canonical target.');
    }

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

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->q);

        return view('livewire.admin.catalog-platform.unresolved-relations-index', [
            'relations' => CatalogUnresolvedPartRelation::query()
                ->with(['sourcePart.brand', 'resolvedTargetPart.brand', 'source', 'sourceRecord', 'reviewer'])
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->when($this->relationType, fn ($query) => $query->where('relation_type', $this->relationType))
                ->when($this->sourceId, fn ($query) => $query->where('catalog_source_id', (int) $this->sourceId))
                ->when($term !== '', function ($query) use ($term): void {
                    $query->where(function ($query) use ($term): void {
                        $query->where('target_number_raw', 'ilike', "%{$term}%")
                            ->orWhere('target_brand_raw', 'ilike', "%{$term}%")
                            ->orWhereHas('sourcePart', fn ($partQuery) => $partQuery
                                ->where('mpn_raw', 'ilike', "%{$term}%")
                                ->orWhere('name', 'ilike', "%{$term}%"));
                    });
                })
                ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
                ->orderByDesc('confidence')
                ->orderBy('id')
                ->paginate(50),
            'relationTypes' => CatalogUnresolvedPartRelation::query()->distinct()->orderBy('relation_type')->pluck('relation_type'),
            'sources' => CatalogSource::query()->whereHas('records')->orderBy('name')->get(['id', 'name', 'code']),
            'counts' => CatalogUnresolvedPartRelation::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    private function noteFor(int $relationId): ?string
    {
        $note = trim((string) ($this->reviewNotes[$relationId] ?? ''));

        return $note !== '' ? $note : null;
    }
}
