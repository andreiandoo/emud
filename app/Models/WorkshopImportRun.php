<?php

namespace App\Models;

use App\Enums\WorkshopImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WorkshopImportRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WorkshopImportStatus::class,
            'scope' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function start(WorkshopDataSource $source, array $scope = []): self
    {
        $source->update(['last_started_at' => now()]);

        return static::query()->create([
            'uuid' => (string) Str::uuid(),
            'data_source_id' => $source->id,
            'status' => WorkshopImportStatus::Running,
            'scope' => $scope,
            'metadata' => [],
            'started_at' => now(),
        ]);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(WorkshopDataSource::class, 'data_source_id');
    }

    /** Adds to one of the run's counters in a single UPDATE, so parallel jobs never lose a count. */
    public function tally(string $outcome, int $by = 1): void
    {
        if ($by > 0) {
            $this->increment($outcome.'_count', $by);
        }
    }

    /**
     * Metadata written by several jobs at once (one per county) is read and rewritten under a
     * row lock; without it, two counties finishing together would each drop the other's entry.
     */
    public function updateMetadata(callable $change): void
    {
        DB::transaction(function () use ($change): void {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->id);
            $metadata = $change($fresh->metadata ?? []);
            $fresh->forceFill(['metadata' => $metadata])->save();
            $this->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    public function markPartitionCompleted(string $partition, array $summary = []): void
    {
        $this->updateMetadata(function (array $metadata) use ($partition, $summary): array {
            $metadata['completed_partitions'][$partition] = $summary + ['at' => now()->toIso8601String()];

            return $metadata;
        });
    }

    public function markPartitionFailed(string $partition, string $error): void
    {
        $this->updateMetadata(function (array $metadata) use ($partition, $error): array {
            $metadata['failed_partitions'][$partition] = ['error' => Str::limit($error, 500), 'at' => now()->toIso8601String()];

            return $metadata;
        });
    }

    /** @return list<string> */
    public function completedPartitions(): array
    {
        return array_keys($this->fresh()?->metadata['completed_partitions'] ?? []);
    }

    /** @return list<string> */
    public function failedPartitions(): array
    {
        $metadata = $this->fresh()?->metadata ?? [];

        return array_values(array_diff(array_keys($metadata['failed_partitions'] ?? []), array_keys($metadata['completed_partitions'] ?? [])));
    }

    public function finish(?string $errorSummary = null): void
    {
        $this->refresh();
        $failed = $this->failed_count > 0 || $this->failedPartitions() !== [];

        $this->update([
            'status' => $failed ? WorkshopImportStatus::CompletedWithErrors : WorkshopImportStatus::Completed,
            'error_summary' => $errorSummary,
            'completed_at' => now(),
        ]);

        $this->dataSource->update(['last_completed_at' => now()]);
    }

    public function fail(Throwable|string $error): void
    {
        $this->update([
            'status' => WorkshopImportStatus::Failed,
            'error_summary' => Str::limit($error instanceof Throwable ? $error->getMessage() : $error, 4000),
            'completed_at' => now(),
        ]);
    }
}
