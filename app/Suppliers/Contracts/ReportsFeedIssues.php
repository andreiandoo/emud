<?php

namespace App\Suppliers\Contracts;

use App\Suppliers\Data\SupplierFeedIssue;

/**
 * Lets a connector hand back rows it had to drop while streaming.
 *
 * The caller drains periodically so a feed with a million broken rows cannot grow
 * an unbounded array in memory.
 */
interface ReportsFeedIssues
{
    /** @return list<SupplierFeedIssue> Issues collected since the previous call. */
    public function takeIssues(): array;
}
