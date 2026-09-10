<?php

namespace App\Jobs;

use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\RecordNormalizers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-reads stored records without fetching anything: after a parser improvement, or to retry the
 * records that failed. A chunk at a time, so one bad record never holds up the rest.
 */
class NormalizeWorkshopRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param list<int> $recordIds */
    public function __construct(public readonly array $recordIds)
    {
        $this->onQueue('workshops');
    }

    public function handle(RecordNormalizers $normalizers): void
    {
        WorkshopSourceRecord::query()
            ->with('dataSource')
            ->whereIn('id', $this->recordIds)
            ->orderBy('id')
            ->each(fn (WorkshopSourceRecord $record): bool => $normalizers->normalizeSafely($record) || true);
    }
}
