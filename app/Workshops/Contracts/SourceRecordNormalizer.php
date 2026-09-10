<?php

namespace App\Workshops\Contracts;

use App\Models\WorkshopSourceRecord;

/**
 * Turns a stored source record into domain rows. Must be idempotent: running it twice on the
 * same record leaves the database exactly as running it once did.
 */
interface SourceRecordNormalizer
{
    public function normalize(WorkshopSourceRecord $record): void;
}
