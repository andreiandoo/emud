<?php

namespace App\Workshops\Ingestion;

use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Data\StoreResult;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * The raw layer. Every record a source returns is kept here as it came, keyed on
 * (source, record type, external id), before anything interprets it.
 *
 * The content is rewritten, and the record queued for parsing, only when its hash changes. Seeing
 * a record again always moves last_seen_at, because that is the evidence it still exists. A record
 * the source stops returning is never deleted; it is marked not current and kept.
 */
class SourceRecordStore
{
    public function store(WorkshopDataSource $source, SourceRecordData $data, ?WorkshopImportRun $run = null, bool $force = false): StoreResult
    {
        $hash = $data->contentHash();
        $now = now();
        $record = $this->find($source, $data);

        if ($record === null) {
            try {
                $record = WorkshopSourceRecord::query()->create([
                    'data_source_id' => $source->id,
                    'import_run_id' => $run?->id,
                    'record_type' => $data->recordType,
                    'external_id' => $data->externalId,
                    'identity_key' => $data->identityKey,
                    'county_code' => $data->countyCode,
                    'source_reference' => $data->sourceReference,
                    'raw_content' => $data->rawContent,
                    'payload' => $data->payload,
                    'content_hash' => $hash,
                    'http_status' => $data->httpStatus,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'fetched_at' => $now,
                    'content_changed_at' => $now,
                    'parse_status' => WorkshopSourceRecord::STATUS_PENDING,
                    'is_current' => true,
                ]);

                return new StoreResult(StoreResult::CREATED, $record);
            } catch (UniqueConstraintViolationException) {
                // Another job stored the same record a moment ago.
                $record = $this->find($source, $data) ?? throw new \RuntimeException("Source record {$data->externalId} vanished after a unique violation.");
            }
        }

        $seenInThisRun = $run !== null && (int) $record->import_run_id === (int) $run->id;
        $changed = $record->content_hash !== $hash;
        $returned = ! $record->is_current;

        $attributes = [
            'import_run_id' => $run?->id ?? $record->import_run_id,
            'identity_key' => $data->identityKey ?? $record->identity_key,
            'county_code' => $data->countyCode ?? $record->county_code,
            'source_reference' => $data->sourceReference ?? $record->source_reference,
            'http_status' => $data->httpStatus ?? $record->http_status,
            'last_seen_at' => $now,
            'fetched_at' => $now,
            'is_current' => true,
        ];

        if ($changed || $force || $returned) {
            $attributes += [
                'payload' => $data->payload,
                'raw_content' => $data->rawContent,
                'content_hash' => $hash,
                'parse_status' => WorkshopSourceRecord::STATUS_PENDING,
                'parse_error' => null,
            ];
        }

        if ($changed) {
            $attributes['content_changed_at'] = $now;
        }

        $record->forceFill($attributes)->save();

        return new StoreResult(match (true) {
            $seenInThisRun && ! $changed => StoreResult::DUPLICATE,
            $changed || $returned => StoreResult::UPDATED,
            default => StoreResult::UNCHANGED,
        }, $record);
    }

    /**
     * Marks as no longer current every record of this type the source did not return since the
     * given moment, optionally within one county. Returns the ids it retired.
     *
     * @return Collection<int, int>
     */
    public function retireUnseen(WorkshopDataSource $source, string $recordType, CarbonInterface $seenSince, ?string $countyCode = null): Collection
    {
        $ids = WorkshopSourceRecord::query()
            ->where('data_source_id', $source->id)
            ->where('record_type', $recordType)
            ->where('is_current', true)
            ->where('last_seen_at', '<', $seenSince)
            ->when($countyCode !== null, fn ($query) => $query->where('county_code', $countyCode))
            ->pluck('id');

        foreach ($ids->chunk(1000) as $chunk) {
            WorkshopSourceRecord::query()->whereIn('id', $chunk->all())->update(['is_current' => false, 'updated_at' => now()]);
        }

        return $ids;
    }

    private function find(WorkshopDataSource $source, SourceRecordData $data): ?WorkshopSourceRecord
    {
        return WorkshopSourceRecord::query()
            ->where('data_source_id', $source->id)
            ->where('record_type', $data->recordType)
            ->where('external_id', $data->externalId)
            ->first();
    }
}
