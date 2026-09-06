<?php

namespace App\Catalog\Api;

use App\Models\CatalogPart;
use App\Models\VehicleConfiguration;

class CatalogSerializer
{
    public function vehicle(VehicleConfiguration $vehicle): array
    {
        $vehicle->loadMissing(['generation.model.make', 'engine', 'identifiers']);

        return [
            'id' => 'veh_'.$vehicle->id,
            'make' => $vehicle->generation?->model?->make?->name,
            'model' => $vehicle->generation?->model?->name,
            'generation' => $vehicle->generation?->name,
            'commercial_name' => $vehicle->commercial_name,
            'year' => $vehicle->year,
            'model_year_from' => $vehicle->model_year_from,
            'model_year_to' => $vehicle->model_year_to,
            'market' => $vehicle->market,
            'engine' => $vehicle->engine ? [
                'code' => $vehicle->engine->engine_code,
                'name' => $vehicle->engine->name,
                'displacement_cc' => $vehicle->displacement_cc ?? $vehicle->engine->displacement_cc,
                'power_kw' => $vehicle->power_kw ?? $vehicle->engine->power_kw,
                'fuel' => $vehicle->fuel_type ?? $vehicle->engine->fuel_type,
            ] : null,
            'eu' => [
                'type_approval' => $vehicle->eu_type_approval,
                'type' => $vehicle->eu_type,
                'variant' => $vehicle->eu_variant,
                'version' => $vehicle->eu_version,
            ],
            'identifiers' => $vehicle->identifiers->map(fn ($identifier) => [
                'scheme' => $identifier->scheme,
                'namespace' => $identifier->namespace,
                'value' => $identifier->value_raw,
            ])->values(),
            'quality' => ['score' => $vehicle->quality_score],
        ];
    }

    public function part(CatalogPart $part): array
    {
        $part->loadMissing(['brand', 'category', 'numbers']);

        return [
            'id' => 'prt_'.$part->public_id,
            'brand' => $part->brand?->name,
            'mpn' => $part->mpn_raw,
            'name' => $part->name,
            'category' => $part->category ? ['id' => $part->category->id, 'name' => $part->category->name, 'path' => $part->category->full_path] : null,
            'lifecycle_status' => $part->lifecycle_status,
            'numbers' => $part->numbers->map(fn ($number) => [
                'scheme' => $number->scheme,
                'number' => $number->number_raw,
                'brand' => $number->brand?->name,
                'oe_make' => $number->oeMake?->name,
            ])->values(),
            'quality' => ['score' => $part->quality_score],
        ];
    }
}
