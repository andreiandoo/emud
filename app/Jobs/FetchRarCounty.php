<?php

namespace App\Jobs;

use App\Enums\WorkshopImportStatus;
use App\Models\WorkshopImportRun;
use App\Workshops\Data\SourcePartition;
use App\Workshops\Sources\Rar\RarImporter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * One county of one RAR section: a few paced requests, never thousands. Safe to retry, because
 * storing a record that is already stored changes nothing.
 */
class FetchRarCounty implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public function __construct(
        public readonly int $runId,
        public readonly array $partition,
        public readonly bool $force = false,
        public readonly ?int $limit = null,
    ) {
        $this->onQueue('workshops');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [120, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("workshops:rar:{$this->runId}:{$this->partition['key']}"))->expireAfter(1900)->dontRelease()];
    }

    public function handle(RarImporter $importer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $run = WorkshopImportRun::query()->find($this->runId);

        if ($run === null || $run->status === WorkshopImportStatus::Cancelled) {
            return;
        }

        $importer->importPartition($run, SourcePartition::fromArray($this->partition), $this->force, $this->limit);
    }

    public function failed(?Throwable $exception): void
    {
        WorkshopImportRun::query()->find($this->runId)?->markPartitionFailed((string) $this->partition['key'], $exception?->getMessage() ?? 'failed');
    }
}
