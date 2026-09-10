<?php

namespace App\Workshops\Sources\Rar;

use App\Enums\WorkshopImportStatus;
use App\Models\Workshop;
use App\Models\WorkshopAuthorization;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Data\SourcePartition;
use App\Workshops\Data\StoreResult;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Ingestion\SourceRecordStore;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Runs a RAR import: plans it (which counties), then imports one county at a time.
 *
 * Idempotent: a record already stored and unchanged is only marked seen, and a county imported
 * twice ends up exactly as after the first time. Resumable: each finished county is written to
 * the run, and --resume continues with the ones that were not. Nothing a county stops listing is
 * deleted; it is retired (not current) and only after a complete, unlimited pass of that county,
 * and not at all if the county suddenly returns far fewer records than it held.
 */
class RarImporter
{
    public function __construct(
        private RarClient $client,
        private SourceRecordStore $store,
        private RecordNormalizers $normalizers,
        private WorkshopStateRefresher $state,
    ) {}

    public function source(string $section): RarRegistrySource
    {
        return new RarRegistrySource($this->client, strtoupper($section));
    }

    public function dataSource(string $section): WorkshopDataSource
    {
        return WorkshopDataSource::forKey(DataSourceCatalog::rarKey($section));
    }

    /**
     * Starts a run, or with $resume picks up the latest unfinished run of this section, and returns
     * the counties still to fetch.
     *
     * @return array{0: WorkshopImportRun, 1: list<SourcePartition>}
     */
    public function plan(string $section, ?string $county = null, bool $resume = false, array $options = []): array
    {
        $section = strtoupper($section);
        $dataSource = $this->dataSource($section);

        if ($resume && ($run = $this->resumableRun($dataSource)) !== null) {
            $done = $run->completedPartitions();
            $remaining = array_values(array_filter(
                array_map(SourcePartition::fromArray(...), $run->metadata['partitions'] ?? []),
                fn (SourcePartition $partition): bool => ! in_array($partition->key, $done, true),
            ));

            $run->update(['status' => WorkshopImportStatus::Running, 'completed_at' => null, 'error_summary' => null]);
            $run->updateMetadata(function (array $metadata): array {
                $metadata['failed_partitions'] = [];
                $metadata['resumed_at'][] = now()->toIso8601String();

                return $metadata;
            });

            return [$run, $remaining];
        }

        $discovered = $this->source($section)->discover();
        $dataSource->putState('counties', array_map(fn (SourcePartition $partition): array => $partition->toArray(), $discovered));
        $dataSource->putState('counties_discovered_at', now()->toIso8601String());

        $partitions = $county === null ? $discovered : $this->onlyCounty($discovered, $county);

        $run = WorkshopImportRun::start($dataSource, ['section' => $section, 'county' => $county] + $options);
        $run->updateMetadata(fn (array $metadata): array => ['partitions' => array_map(fn (SourcePartition $partition): array => $partition->toArray(), $partitions)] + $metadata);

        return [$run, $partitions];
    }

    /** @return array{seen: int, created: int, updated: int, unchanged: int, duplicate: int, failed: int, retired: int} */
    public function importPartition(WorkshopImportRun $run, SourcePartition $partition, bool $force = false, ?int $limit = null, bool $dryRun = false): array
    {
        $section = (string) ($run->scope['section'] ?? 'SERVICE');
        $dataSource = $run->dataSource;
        $startedAt = now()->startOfSecond();
        $counts = ['seen' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'duplicate' => 0, 'failed' => 0, 'retired' => 0];
        $heldBefore = $this->currentCount($dataSource, $partition);

        try {
            foreach ($this->source($section)->fetch($partition) as $data) {
                if ($limit !== null && $counts['seen'] >= $limit) {
                    break;
                }

                $counts['seen']++;
                $run->tally('discovered');

                if ($dryRun) {
                    continue;
                }

                try {
                    $result = $this->store->store($dataSource, $data, $run, $force);
                    $counts[$result->outcome]++;
                    $run->tally('fetched');
                    $run->tally($result->outcome === StoreResult::DUPLICATE ? 'skipped' : $result->outcome);

                    if ($result->needsParsing() && ! $this->normalizers->normalizeSafely($result->record)) {
                        $counts['failed']++;
                        $run->tally('failed');
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $counts['failed']++;
                    $run->tally('failed');
                }
            }
        } catch (Throwable $exception) {
            $run->markPartitionFailed($partition->key, $exception->getMessage());

            throw $exception;
        }

        if (! $dryRun && $limit === null) {
            $counts['retired'] = $this->retire($run, $dataSource, $partition, $startedAt, $heldBefore, $counts['seen']);
        }

        $run->markPartitionCompleted($partition->key, $counts);

        return $counts;
    }

    private function retire(WorkshopImportRun $run, WorkshopDataSource $dataSource, SourcePartition $partition, CarbonInterface $startedAt, int $heldBefore, int $seen): int
    {
        if ($partition->countyCode === null) {
            return 0;
        }

        if ($heldBefore >= 20 && $seen < $heldBefore * (float) config('workshops.rar.retire_guard_ratio')) {
            $run->updateMetadata(function (array $metadata) use ($partition, $seen, $heldBefore): array {
                $metadata['retire_skipped'][$partition->key] = "returned {$seen} of {$heldBefore} held; nothing retired";

                return $metadata;
            });

            return 0;
        }

        $ids = $this->store->retireUnseen($dataSource, 'authorization', $startedAt, $partition->countyCode);

        if ($ids->isEmpty()) {
            return 0;
        }

        foreach ($ids->chunk(1000) as $chunk) {
            WorkshopAuthorization::query()->whereIn('source_record_id', $chunk->all())->update(['is_current' => false, 'updated_at' => now()]);
        }

        $workshopIds = WorkshopSourceLink::query()->whereIn('source_record_id', $ids->all())->pluck('workshop_id')->unique();

        Workshop::query()->whereIn('id', $workshopIds->all())->each(fn (Workshop $workshop) => $this->state->refresh($workshop));

        $run->tally('retired', $ids->count());

        return $ids->count();
    }

    private function currentCount(WorkshopDataSource $dataSource, SourcePartition $partition): int
    {
        if ($partition->countyCode === null) {
            return 0;
        }

        return WorkshopSourceRecord::query()
            ->where('data_source_id', $dataSource->id)
            ->where('record_type', 'authorization')
            ->where('county_code', $partition->countyCode)
            ->where('is_current', true)
            ->count();
    }

    private function resumableRun(WorkshopDataSource $dataSource): ?WorkshopImportRun
    {
        return WorkshopImportRun::query()
            ->where('data_source_id', $dataSource->id)
            ->whereIn('status', [WorkshopImportStatus::Running, WorkshopImportStatus::Failed, WorkshopImportStatus::CompletedWithErrors])
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<SourcePartition>  $partitions
     * @return list<SourcePartition>
     */
    private function onlyCounty(array $partitions, string $county): array
    {
        $code = RomanianCounties::resolve($county);
        $folded = TextNormalizer::fold($county);

        $matching = array_values(array_filter(
            $partitions,
            fn (SourcePartition $partition): bool => ($code !== null && $partition->countyCode === $code) || TextNormalizer::fold($partition->label) === $folded,
        ));

        if ($matching === []) {
            throw new InvalidArgumentException("The registry lists no county matching [{$county}].");
        }

        return $matching;
    }
}
