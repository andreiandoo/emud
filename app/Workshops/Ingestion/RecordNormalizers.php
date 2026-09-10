<?php

namespace App\Workshops\Ingestion;

use App\Models\WorkshopSourceRecord;
use App\Workshops\Contracts\SourceRecordNormalizer;
use App\Workshops\Sources\Onrc\OnrcRecordNormalizer;
use App\Workshops\Sources\Osm\OsmRecordNormalizer;
use App\Workshops\Sources\Rar\RarParseException;
use App\Workshops\Sources\Rar\RarRecordNormalizer;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

/**
 * Which normaliser reads which source, and a way to run one that records failure on the record
 * instead of stopping a whole import.
 */
class RecordNormalizers
{
    public function __construct(private Container $container) {}

    public function for(WorkshopSourceRecord $record): SourceRecordNormalizer
    {
        $key = (string) $record->dataSource?->key;

        return match (true) {
            DataSourceCatalog::rarSectionFor($key) !== null => $this->container->make(RarRecordNormalizer::class),
            $key === DataSourceCatalog::ONRC => $this->container->make(OnrcRecordNormalizer::class),
            $key === DataSourceCatalog::OSM => $this->container->make(OsmRecordNormalizer::class),
            default => throw new InvalidArgumentException("No normaliser reads records of source [{$key}]."),
        };
    }

    /** True when the record was normalised; false when it failed and says why. */
    public function normalizeSafely(WorkshopSourceRecord $record): bool
    {
        $record->loadMissing('dataSource');

        try {
            $this->for($record)->normalize($record);

            return true;
        } catch (Throwable $exception) {
            $record->markFailed($exception->getMessage());

            // A record that cannot be read is data to look at, not an incident; anything else is.
            if (! $exception instanceof RarParseException && ! $exception instanceof InvalidArgumentException) {
                report($exception);
            }

            return false;
        }
    }
}
