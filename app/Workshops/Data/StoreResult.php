<?php

namespace App\Workshops\Data;

use App\Models\WorkshopSourceRecord;

readonly class StoreResult
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const UNCHANGED = 'unchanged';

    /** The same record twice in one run, as RAR does when a page boundary repeats a row. */
    public const DUPLICATE = 'duplicate';

    public function __construct(public string $outcome, public WorkshopSourceRecord $record) {}

    public function needsParsing(): bool
    {
        return $this->record->parse_status === WorkshopSourceRecord::STATUS_PENDING;
    }
}
