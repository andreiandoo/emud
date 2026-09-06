<?php

namespace App\Catalog\Sources\Contracts;

use App\Models\CatalogSource;

interface CatalogSourceReleaseProvider
{
    /** @return array<string, mixed>|null */
    public function release(CatalogSource $source, string $mode = 'catalog'): ?array;
}
