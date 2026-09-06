<?php

namespace App\Catalog\Sources\Contracts;

use App\Models\CatalogSource;

interface CatalogSourceConnector
{
    /** @return iterable<array<string, mixed>> */
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable;

    /** @return array<string, mixed> */
    public function testConnection(CatalogSource $source): array;
}
