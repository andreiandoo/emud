<?php

namespace App\Workshops\Contracts;

use App\Workshops\Data\SourcePartition;
use App\Workshops\Data\SourceRecordData;

/**
 * A source of workshop records. Adapters only fetch; storing, change detection and turning
 * records into workshops happen elsewhere, so an adapter can be replaced without touching them.
 */
interface WorkshopSource
{
    /** The workshop_data_sources key this adapter feeds. */
    public function key(): string;

    /**
     * The independent units of work, discovered from the source itself where it can tell us.
     *
     * @return list<SourcePartition>
     */
    public function discover(): array;

    /** @return iterable<SourceRecordData> */
    public function fetch(SourcePartition $partition): iterable;
}
