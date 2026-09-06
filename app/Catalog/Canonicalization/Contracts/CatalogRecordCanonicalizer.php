<?php

namespace App\Catalog\Canonicalization\Contracts;

use App\Catalog\Canonicalization\CanonicalizationResult;
use App\Models\CatalogSourceRecord;

interface CatalogRecordCanonicalizer
{
    public function canonicalize(CatalogSourceRecord $record): CanonicalizationResult;
}
