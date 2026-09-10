<?php

namespace App\Workshops\Matching;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;

/**
 * Keeps the matching decision for a record. A decision a person already took (confirmed or
 * rejected in the admin) is never overwritten by the next automatic run.
 */
class RecordMatchRecorder
{
    public function record(WorkshopSourceRecord $record, string $targetType, MatchDecision $decision): WorkshopRecordMatch
    {
        $existing = WorkshopRecordMatch::query()->where('source_record_id', $record->id)->where('target_type', $targetType)->first();

        if ($existing !== null && $existing->reviewed_by !== null) {
            return $existing;
        }

        return WorkshopRecordMatch::query()->updateOrCreate(
            ['source_record_id' => $record->id, 'target_type' => $targetType],
            [
                'target_id' => $decision->targetId,
                'status' => $decision->status,
                'method' => $decision->method,
                'score' => $decision->score,
                'candidates' => array_slice($decision->candidates, 0, 10),
                'evidence' => $decision->evidence,
                'decided_at' => now(),
            ],
        );
    }

    /** The reviewed decision for a record, if a person has taken one. */
    public function reviewed(WorkshopSourceRecord $record, string $targetType): ?WorkshopRecordMatch
    {
        return WorkshopRecordMatch::query()
            ->where('source_record_id', $record->id)
            ->where('target_type', $targetType)
            ->whereNotNull('reviewed_by')
            ->whereIn('status', [WorkshopRecordMatchStatus::Matched, WorkshopRecordMatchStatus::Rejected])
            ->first();
    }
}
