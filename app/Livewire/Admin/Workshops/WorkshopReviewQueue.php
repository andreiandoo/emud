<?php

namespace App\Livewire\Admin\Workshops;

use App\Enums\WorkshopMatchStatus;
use App\Enums\WorkshopRecordMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopCompany;
use App\Models\WorkshopMatchCandidate;
use App\Models\WorkshopRecordMatch;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Matching\WorkshopMerger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What the matchers would not decide alone: pairs of workshops that may be one place, and source
 * records (an OSM point, an ONRC company) that fit more than one candidate or fit only loosely.
 * Every decision here is recorded with who took it and is never overturned by the next import.
 */
#[Layout('layouts::admin')]
class WorkshopReviewQueue extends Component
{
    use WithPagination;

    #[Url(except: 'duplicates')]
    public string $tab = 'duplicates';

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->resetPage();
        }
    }

    public function merge(int $candidateId): void
    {
        $pair = WorkshopMatchCandidate::query()->with(['workshopA', 'workshopB'])->findOrFail($candidateId);

        if ($pair->workshopA === null || $pair->workshopB === null || $pair->workshopA->merged_into_id !== null || $pair->workshopB->merged_into_id !== null) {
            $pair->update(['status' => WorkshopMatchStatus::Rejected, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

            return;
        }

        [$survivor, $duplicate] = WorkshopMerger::order($pair->workshopA, $pair->workshopB);
        app(WorkshopMerger::class)->merge($survivor, $duplicate, (array) $pair->evidence, Auth::id(), (int) $pair->score);
        session()->flash('status', "„{$duplicate->name}” a fost unit în „{$survivor->name}”.");
    }

    public function keepApart(int $candidateId): void
    {
        WorkshopMatchCandidate::query()->whereKey($candidateId)->update(['status' => WorkshopMatchStatus::Rejected, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
    }

    public function linkRecord(int $matchId, int $targetId): void
    {
        $match = WorkshopRecordMatch::query()->with('sourceRecord.dataSource')->findOrFail($matchId);
        $match->update(['status' => WorkshopRecordMatchStatus::Matched, 'target_id' => $targetId, 'method' => 'reviewed', 'reviewed_by' => Auth::id(), 'decided_at' => now()]);
        app(RecordNormalizers::class)->normalizeSafely($match->sourceRecord);
    }

    public function rejectRecord(int $matchId): void
    {
        $match = WorkshopRecordMatch::query()->with('sourceRecord.dataSource')->findOrFail($matchId);
        $match->update(['status' => WorkshopRecordMatchStatus::Rejected, 'target_id' => null, 'reviewed_by' => Auth::id(), 'decided_at' => now()]);
        app(RecordNormalizers::class)->normalizeSafely($match->sourceRecord);
    }

    public function render()
    {
        $pairs = WorkshopMatchCandidate::query()
            ->with(['workshopA.company:id,cui', 'workshopB.company:id,cui'])
            ->where('status', WorkshopMatchStatus::Pending)
            ->orderByDesc('score')
            ->paginate(25, pageName: 'pairs');

        $records = WorkshopRecordMatch::query()
            ->with('sourceRecord.dataSource:id,key,name')
            ->whereNull('reviewed_by')
            ->whereIn('status', [WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])
            ->orderByDesc('score')
            ->paginate(25, pageName: 'records');

        $targetIds = collect($records->items())->flatMap(fn (WorkshopRecordMatch $match) => collect((array) $match->candidates)->map(fn (array $candidate) => $candidate['workshop_id'] ?? $candidate['company_id'] ?? null)->push($match->target_id))->filter()->unique();

        return view('livewire.admin.workshops.review', [
            'pairs' => $pairs,
            'records' => $records,
            'workshopNames' => Workshop::query()->whereIn('id', $targetIds->all())->pluck('name', 'id'),
            'companyNames' => WorkshopCompany::query()->whereIn('id', $targetIds->all())->pluck('legal_name', 'id'),
            'counts' => [
                'duplicates' => WorkshopMatchCandidate::query()->where('status', WorkshopMatchStatus::Pending)->count(),
                'records' => WorkshopRecordMatch::query()->whereNull('reviewed_by')->whereIn('status', [WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])->count(),
            ],
        ]);
    }
}
