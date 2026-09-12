<?php

namespace App\Catalog\Api;

use Illuminate\Database\Eloquent\Builder;

class CatalogPublicationScope
{
    /**
     * Limit a make/model/generation listing to branches that lead to a vehicle this API may
     * publish. Without it the tree advertises 13,138 vPIC manufacturers — trailer builders and
     * welding shops included — of which only a few hundred have a car behind them, and a caller
     * walking the tree hits an empty level.
     */
    public function withVisibleConfigurations(Builder $query, string $relationPath): Builder
    {
        return $query->whereHas(
            $relationPath,
            fn ($configurations) => $this->visibleEntity($configurations, 'vehicle_configuration', 'vehicle_configurations.id'),
        );
    }

    /**
     * An entity is public only while BOTH conditions hold: an assertion published it for
     * redistribution, and the source behind that assertion is still allowed to be republished.
     *
     * The assertion's own flag is a snapshot taken when it was written (see
     * SourceAssertionWriter), so on its own it cannot answer the question an operator asks by
     * clearing "allow API redistribution" on a source: stop publishing this. Nothing rewrites
     * those snapshots, so checking the live source flag here is what makes the revocation take
     * effect — on every already-written assertion, immediately, without a backfill.
     */
    public function visibleEntity(Builder $query, string $entityType, string $qualifiedId = 'id'): Builder
    {
        return $query->whereExists(function ($subquery) use ($entityType, $qualifiedId): void {
            $subquery->selectRaw('1')
                ->from('catalog_source_assertions')
                ->join('catalog_sources', 'catalog_sources.id', '=', 'catalog_source_assertions.catalog_source_id')
                ->whereColumn('catalog_source_assertions.entity_id', $qualifiedId)
                ->where('catalog_source_assertions.entity_type', $entityType)
                ->where('catalog_source_assertions.status', 'published')
                ->where('catalog_source_assertions.api_redistributable', true)
                ->where('catalog_sources.allow_api_redistribution', true);
        });
    }
}
