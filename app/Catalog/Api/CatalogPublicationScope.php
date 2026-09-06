<?php

namespace App\Catalog\Api;

use Illuminate\Database\Eloquent\Builder;

class CatalogPublicationScope
{
    public function visibleEntity(Builder $query, string $entityType, string $qualifiedId = 'id'): Builder
    {
        return $query->whereExists(function ($subquery) use ($entityType, $qualifiedId): void {
            $subquery->selectRaw('1')
                ->from('catalog_source_assertions')
                ->whereColumn('catalog_source_assertions.entity_id', $qualifiedId)
                ->where('catalog_source_assertions.entity_type', $entityType)
                ->where('catalog_source_assertions.status', 'published')
                ->where('catalog_source_assertions.api_redistributable', true);
        });
    }
}
