<?php

namespace App\Suppliers\Contracts;

use App\Models\Supplier;
use App\Suppliers\Data\SupplierConnectionResult;

/**
 * Reachability check that never imports anything.
 *
 * A connector implementing this must not mutate catalogue data and must keep its
 * own timeout short, because this is the one supplier network call allowed to run
 * inside an admin request rather than in a queued job.
 */
interface SupportsConnectionTest
{
    public function testConnection(Supplier $supplier, string $mode = 'catalog'): SupplierConnectionResult;
}
