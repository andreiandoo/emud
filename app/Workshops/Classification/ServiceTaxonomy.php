<?php

namespace App\Workshops\Classification;

use App\Models\WorkshopServiceType;
use InvalidArgumentException;

/**
 * Our own service vocabulary, independent of how any source words it.
 *
 * RAR codes, OSM tags and website phrases are all mapped onto these keys, each with its own
 * evidence type, so "which workshops do automatic gearboxes" has one answer whatever the source.
 * The rows are created on first use and kept in step with this list; nothing depends on a seeder.
 */
class ServiceTaxonomy
{
    /** key => [Romanian name, parent key] in display order. */
    public const DEFINITIONS = [
        'general_repair' => ['Reparații generale', null],
        'maintenance' => ['Întreținere și revizii', null],
        'engine' => ['Motor', null],
        'petrol' => ['Motoare pe benzină (MAS)', 'engine'],
        'diesel' => ['Motoare diesel (MAC)', 'engine'],
        'fuel_injection' => ['Injecție și alimentare', 'engine'],
        'transmission' => ['Transmisie', null],
        'manual_transmission' => ['Cutii de viteze manuale', 'transmission'],
        'automatic_transmission' => ['Cutii de viteze automate', 'transmission'],
        'transfer_case' => ['Cutii de transfer (reductoare)', 'transmission'],
        'differential' => ['Diferențiale și punți motoare', 'transmission'],
        '4x4_drivetrain' => ['Transmisii 4x4 / tracțiune integrală', 'transmission'],
        'suspension' => ['Suspensie', null],
        'axles' => ['Punți și axe', 'suspension'],
        'offroad_suspension' => ['Suspensii off-road și înălțări', 'suspension'],
        'steering' => ['Direcție', null],
        'brakes' => ['Frâne', null],
        'electrical' => ['Instalație electrică', null],
        'electronics' => ['Electronică auto', 'electrical'],
        'diagnostics' => ['Diagnoză computerizată', null],
        'adas_calibration' => ['Calibrare sisteme ADAS', 'electronics'],
        'bodywork' => ['Tinichigerie și caroserie', null],
        'painting' => ['Vopsitorie', 'bodywork'],
        'glass' => ['Parbrize și geamuri', 'bodywork'],
        'chassis_repair' => ['Reparații și redresare șasiu', 'bodywork'],
        'rustproofing' => ['Protecție anticorozivă', 'bodywork'],
        'tyres' => ['Anvelope și vulcanizare', null],
        'wheel_alignment' => ['Geometrie roți', 'tyres'],
        'wheel_balancing' => ['Echilibrare roți', 'tyres'],
        'air_conditioning' => ['Climatizare', null],
        'exhaust' => ['Sistem de evacuare', null],
        'dpf' => ['Filtru de particule (DPF/FAP)', 'exhaust'],
        'egr' => ['Supapă EGR', 'exhaust'],
        'hybrid' => ['Vehicule hibride', null],
        'electric_vehicle' => ['Vehicule electrice', null],
        'truck_service' => ['Camioane și autobuze', null],
        'commercial_vehicle_service' => ['Vehicule comerciale și utilitare', null],
        'motorcycle_service' => ['Motociclete și ATV', null],
        'itp' => ['Inspecție tehnică periodică (ITP)', null],
        'gpl' => ['Instalații GPL', null],
        'gnc' => ['Instalații GNC', null],
        'tachograph' => ['Tahografe și limitatoare de viteză', null],
        'towing' => ['Tractări și asistență rutieră', null],
        'detailing' => ['Detailing și cosmetică auto', null],
        'dismantling' => ['Dezmembrări', null],
        'vehicle_modifications' => ['Modificări omologate (RAR B4)', null],
        'offroad_modifications' => ['Modificări și echipări off-road', 'vehicle_modifications'],
        'fabrication' => ['Fabricație și prelucrări', 'vehicle_modifications'],
        'welding' => ['Sudură', 'vehicle_modifications'],
        'accessories_installation' => ['Montaj accesorii', null],
        'winch_installation' => ['Montaj troliu', 'accessories_installation'],
        'snorkel_installation' => ['Montaj snorkel', 'accessories_installation'],
        'lighting_installation' => ['Montaj proiectoare și iluminat', 'accessories_installation'],
        'towbar_installation' => ['Montaj cârlig de remorcare', 'accessories_installation'],
        'other' => ['Alte servicii', null],
    ];

    /** @var array<string, int>|null */
    private ?array $ids = null;

    public function idFor(string $key): int
    {
        $ids = $this->ids();

        if (! isset($ids[$key])) {
            throw new InvalidArgumentException("Unknown workshop service [{$key}].");
        }

        return $ids[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::DEFINITIONS[$key]);
    }

    public static function name(string $key): string
    {
        return self::DEFINITIONS[$key][0] ?? $key;
    }

    /** @return array<string, int> key => id */
    public function ids(): array
    {
        if ($this->ids !== null) {
            return $this->ids;
        }

        $existing = WorkshopServiceType::query()->pluck('id', 'key')->all();
        $position = 0;

        foreach (self::DEFINITIONS as $key => [$name]) {
            $position++;

            if (! isset($existing[$key])) {
                $existing[$key] = WorkshopServiceType::query()->firstOrCreate(['key' => $key], ['name' => $name, 'position' => $position])->id;
            }
        }

        foreach (self::DEFINITIONS as $key => [$name, $parent]) {
            WorkshopServiceType::query()->whereKey($existing[$key])->where(function ($query) use ($name, $parent, $existing): void {
                $query->where('name', '!=', $name)
                    ->orWhere(fn ($query) => $parent === null ? $query->whereNotNull('parent_id') : $query->whereNull('parent_id')->orWhere('parent_id', '!=', $existing[$parent]));
            })->update(['name' => $name, 'parent_id' => $parent === null ? null : $existing[$parent], 'updated_at' => now()]);
        }

        return $this->ids = $existing;
    }
}
