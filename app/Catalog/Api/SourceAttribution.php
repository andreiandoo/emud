<?php

namespace App\Catalog\Api;

use App\Models\CatalogPart;
use App\Models\CatalogSource;
use App\Models\CatalogSourceAssertion;
use App\Models\VehicleConfiguration;
use Illuminate\Support\Collection;

/**
 * Who the facts in a response came from.
 *
 * Several of the sources this catalogue is allowed to republish are only allowed on condition
 * that they are credited: the EEA dataset is CC BY, and NHTSA asks that attribution survives
 * copying. Storing `attribution_required` and never printing it would put the API in breach of
 * the licence it relies on, so every response carries the credit for the rows it actually
 * returned rather than a blanket notice in the documentation.
 *
 * Resolved per request; accumulate during serialization, then render once into `meta`.
 */
class SourceAttribution
{
    /** @var array<string, array<int, int>> */
    private array $entityIds = [];

    /** @var array<int, int> */
    private array $sourceIds = [];

    /**
     * @param  iterable<int|string>  $ids
     */
    public function addEntities(string $entityType, iterable $ids): self
    {
        foreach ($ids as $id) {
            $this->entityIds[$entityType][] = (int) $id;
        }

        return $this;
    }

    /**
     * @param  iterable<int|string|null>  $ids
     */
    public function addSources(iterable $ids): self
    {
        foreach ($ids as $id) {
            if ($id !== null) {
                $this->sourceIds[] = (int) $id;
            }
        }

        return $this;
    }

    /**
     * Credit every source behind a serialized vehicle: the assertion that publishes it, plus the
     * source of each identifier printed alongside it, which need not be the same source.
     *
     * @param  iterable<VehicleConfiguration>  $vehicles
     */
    public function addVehicles(iterable $vehicles): self
    {
        $vehicles = Collection::make($vehicles);
        $this->addEntities('vehicle_configuration', $vehicles->pluck('id'));
        $this->addSources($vehicles->flatMap(
            fn ($vehicle) => $vehicle->relationLoaded('identifiers')
                ? $vehicle->identifiers->pluck('catalog_source_id')
                : [],
        ));

        return $this;
    }

    /**
     * @param  iterable<CatalogPart>  $parts
     */
    public function addParts(iterable $parts): self
    {
        $parts = Collection::make($parts);
        $this->addEntities('catalog_part', $parts->pluck('id'));
        $this->addSources($parts->flatMap(
            fn ($part) => $part->relationLoaded('numbers') ? $part->numbers->pluck('catalog_source_id') : [],
        ));

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        $sourceIds = $this->sourceIds;

        foreach ($this->entityIds as $entityType => $ids) {
            $ids = array_values(array_unique($ids));
            if ($ids === []) {
                continue;
            }

            $sourceIds = [...$sourceIds, ...CatalogSourceAssertion::query()
                ->where('entity_type', $entityType)
                ->whereIn('entity_id', $ids)
                ->where('status', 'published')
                ->where('api_redistributable', true)
                ->distinct()
                ->pluck('catalog_source_id')
                ->map(fn ($id) => (int) $id)
                ->all()];
        }

        $sourceIds = array_values(array_filter(array_unique($sourceIds)));
        if ($sourceIds === []) {
            return [];
        }

        return CatalogSource::query()
            ->whereIn('id', $sourceIds)
            // A source that may not be republished must not be named either: the fact that a
            // restricted catalogue was consulted is itself something the licence may cover.
            ->where('allow_api_redistribution', true)
            ->orderBy('code')
            ->get()
            ->map(fn (CatalogSource $source) => array_filter([
                'code' => $source->code,
                'name' => $source->name,
                'license' => $source->license_name,
                'license_url' => $source->license_url,
                'url' => $source->base_url,
                'attribution_required' => (bool) $source->attribution_required,
            ], fn ($value) => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    /**
     * The line a consumer has to reproduce. Null when nothing in the response asks for credit.
     */
    public function notice(array $attribution): ?string
    {
        $required = array_values(array_filter($attribution, fn (array $source) => ($source['attribution_required'] ?? false) === true));
        if ($required === []) {
            return null;
        }

        $names = array_map(
            fn (array $source) => isset($source['license']) ? "{$source['name']} ({$source['license']})" : $source['name'],
            $required,
        );

        return 'Data in this response must be credited to: '.implode('; ', $names).'.';
    }

    /**
     * Render the attribution block for a response envelope.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $attribution = $this->toArray();
        if ($attribution === []) {
            return [];
        }

        return array_filter([
            'attribution' => $attribution,
            'license_notice' => $this->notice($attribution),
        ], fn ($value) => $value !== null);
    }
}
