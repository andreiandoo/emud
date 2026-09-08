<?php

namespace App\Suppliers\Data;

use App\Enums\SupplierSyncErrorType;

/**
 * A feed row the connector could not turn into a SupplierRecord.
 *
 * These never reach the importer, so without reporting them a supplier could
 * change a column name and have most of its catalogue quietly disappear while
 * every run still finished "successfully".
 */
readonly class SupplierFeedIssue
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public SupplierSyncErrorType $type,
        public string $message,
        public ?string $externalIdentifier = null,
        public array $raw = [],
    ) {}
}
