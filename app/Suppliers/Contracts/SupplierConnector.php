<?php

namespace App\Suppliers\Contracts;

use App\Models\Supplier;
use App\Suppliers\Data\SupplierRecord;

interface SupplierConnector
{
    /** @return iterable<SupplierRecord> */
    public function records(Supplier $supplier, string $mode): iterable;
}
