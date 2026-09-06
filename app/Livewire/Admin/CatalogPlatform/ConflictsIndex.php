<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogConflict;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class ConflictsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $type = '';

    #[Url]
    public string $severity = '';

    #[Url]
    public string $q = '';

    public array $notes = [];

    public function resolve(int $conflictId, string $action = 'reviewed'): void
    {
        $allowed = ['reviewed', 'accept_source', 'keep_canonical', 'not_a_conflict'];
        abort_unless(in_array($action, $allowed, true), 422);

        $conflict = CatalogConflict::query()->findOrFail($conflictId);
        $conflict->update([
            'status' => 'resolved',
            'resolution' => [
                'action' => $action,
                'note' => $this->noteFor($conflictId),
                'resolved_at' => now()->toIso8601String(),
            ],
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        unset($this->notes[$conflictId]);
        session()->flash('status', 'Conflict resolution recorded.');
    }

    public function dismiss(int $conflictId): void
    {
        $conflict = CatalogConflict::query()->findOrFail($conflictId);
        $conflict->update([
            'status' => 'dismissed',
            'resolution' => [
                'action' => 'dismissed',
                'note' => $this->noteFor($conflictId),
                'resolved_at' => now()->toIso8601String(),
            ],
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        unset($this->notes[$conflictId]);
        session()->flash('status', 'Conflict dismissed.');
    }

    public function reopen(int $conflictId): void
    {
        $conflict = CatalogConflict::query()->findOrFail($conflictId);
        $history = $conflict->resolution ?? [];
        $history['reopened'] = [
            'by' => auth()->id(),
            'at' => now()->toIso8601String(),
            'note' => $this->noteFor($conflictId),
        ];

        $conflict->update([
            'status' => 'open',
            'resolution' => $history,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        unset($this->notes[$conflictId]);
        session()->flash('status', 'Conflict reopened.');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedSeverity(): void
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

        return view('livewire.admin.catalog-platform.conflicts-index', [
            'conflicts' => CatalogConflict::query()
                ->when($this->status, fn ($query) => $query->where('status', $this->status))
                ->when($this->type, fn ($query) => $query->where('entity_type', $this->type))
                ->when($this->severity, fn ($query) => $query->where('severity', $this->severity))
                ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term): void {
                    $query->where('entity_type', 'ilike', "%{$term}%")
                        ->orWhere('field_or_relation', 'ilike', "%{$term}%")
                        ->orWhereRaw('CAST(details AS TEXT) ILIKE ?', ["%{$term}%"]);
                }))
                ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
                ->latest()
                ->paginate(50),
            'types' => CatalogConflict::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'counts' => CatalogConflict::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    private function noteFor(int $conflictId): ?string
    {
        $note = trim((string) ($this->notes[$conflictId] ?? ''));

        return $note !== '' ? $note : null;
    }
}
