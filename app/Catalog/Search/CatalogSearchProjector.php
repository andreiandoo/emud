<?php

namespace App\Catalog\Search;

use App\Models\CatalogPart;
use App\Models\CatalogSearchDocument;
use App\Models\VehicleAlias;
use App\Models\VehicleConfiguration;

class CatalogSearchProjector
{
    /** @var array<string, array<int, string>> */
    private array $aliasCache = [];

    public function projectPart(CatalogPart $part): CatalogSearchDocument
    {
        $part->loadMissing(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source']);
        $publicNumbers = $part->numbers->filter(fn ($number) => (bool) $number->source?->allow_api_redistribution);
        $terms = [
            $part->name,
            $part->brand?->name,
            $part->category?->name,
        ];

        foreach ($publicNumbers as $number) {
            array_push(
                $terms,
                $number->number_raw,
                $number->number_normalized,
                $number->number_compact,
                $number->brand?->name,
                $number->oeMake?->name,
                $number->scheme,
                $number->namespace,
            );
        }

        return $this->store('catalog_part', (int) $part->id, [
            'title' => $part->name ?: $part->mpn_raw,
            'subtitle' => $publicNumbers->first()?->number_raw,
            'brand' => $part->brand?->name,
            'category' => $part->category?->name,
            'search_text' => $this->searchText($terms),
            'metadata' => [
                'public_id' => (string) $part->public_id,
                'lifecycle_status' => $part->lifecycle_status,
                'public_number_count' => $publicNumbers->count(),
            ],
        ]);
    }

    public function projectVehicle(VehicleConfiguration $vehicle): CatalogSearchDocument
    {
        $vehicle->loadMissing(['generation.model.make', 'engine', 'identifiers.source']);
        $generation = $vehicle->generation;
        $model = $generation?->model;
        $make = $model?->make;
        $publicIdentifiers = $vehicle->identifiers->filter(fn ($identifier) => (bool) $identifier->source?->allow_api_redistribution);
        $terms = [
            $make?->name,
            $model?->name,
            $generation?->name,
            $vehicle->commercial_name,
            $vehicle->year,
            $vehicle->model_year_from,
            $vehicle->model_year_to,
            $vehicle->market,
            $vehicle->fuel_type,
            $vehicle->drive_type,
            $vehicle->engine?->name,
            $vehicle->engine?->engine_code,
            $vehicle->eu_type_approval,
            $vehicle->eu_type,
            $vehicle->eu_variant,
            $vehicle->eu_version,
        ];

        foreach ([
            ['vehicle_make', $make?->id],
            ['vehicle_model', $model?->id],
            ['vehicle_generation', $generation?->id],
        ] as [$entityType, $entityId]) {
            if ($entityId) {
                array_push($terms, ...$this->aliases((string) $entityType, (int) $entityId));
            }
        }

        foreach ($publicIdentifiers as $identifier) {
            array_push($terms, $identifier->value_raw, $identifier->value_normalized, $identifier->scheme, $identifier->namespace);
        }

        $title = trim(implode(' ', array_filter([$make?->name, $model?->name, $generation?->name])));

        return $this->store('vehicle_configuration', (int) $vehicle->id, [
            'title' => $title !== '' ? $title : $vehicle->commercial_name,
            'subtitle' => trim(implode(' ', array_filter([$vehicle->year, $vehicle->engine?->engine_code]))),
            'brand' => $make?->name,
            'category' => null,
            'search_text' => $this->searchText($terms),
            'metadata' => [
                'year' => $vehicle->year,
                'market' => $vehicle->market,
                'fuel_type' => $vehicle->fuel_type,
                'quality_score' => $vehicle->quality_score,
                'public_identifier_count' => $publicIdentifiers->count(),
            ],
        ]);
    }

    public function forget(string $entityType, int $entityId): void
    {
        CatalogSearchDocument::query()->where('entity_type', $entityType)->where('entity_id', $entityId)->delete();
    }

    private function store(string $entityType, int $entityId, array $payload): CatalogSearchDocument
    {
        return CatalogSearchDocument::query()->updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            [...$payload, 'indexed_at' => now()],
        );
    }

    /** @return array<int, string> */
    private function aliases(string $entityType, int $entityId): array
    {
        $key = $entityType.':'.$entityId;

        return $this->aliasCache[$key] ??= VehicleAlias::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereHas('source', fn ($query) => $query->where('allow_api_redistribution', true))
            ->pluck('alias')
            ->filter()
            ->map(fn ($alias) => (string) $alias)
            ->values()
            ->all();
    }

    private function searchText(array $terms): string
    {
        return collect($terms)
            ->flatten()
            ->filter(fn ($term) => $term !== null && trim((string) $term) !== '')
            ->map(fn ($term) => trim((string) $term))
            ->unique(fn ($term) => mb_strtolower($term))
            ->implode(' ');
    }
}
