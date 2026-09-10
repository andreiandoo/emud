<?php

namespace App\Workshops\Sources\Rar;

use App\Models\WorkshopAuthorization;

/**
 * From RAR's codes to our service keys: an interpretation, stored separately from the codes.
 *
 * Every code is mapped on its own wording and inherits nothing from its parent, because RAR lists
 * each sub-activity it authorises. The confidence says how directly the wording names the service:
 * "A1.2.2. transmisie automată" is automatic gearboxes at 95, "A1.3.1. roţi" is tyre work at 80.
 */
class RarActivityMap
{
    /** Transmission codes whose wording covers drive "pe o axă sau pe mai multe axe". */
    public const MULTI_AXLE_TRANSMISSION = ['A1_2_1_1', 'A1_2_1_2', 'A1_2_2_1', 'A1_2_2_2'];

    /** "cu tracţiune permanentă pe mai multe axe": permanent all-wheel drive, named outright. */
    public const PERMANENT_ALL_WHEEL_DRIVE = ['A1_2_1_3', 'A1_2_2_3'];

    public const HYBRID = ['A1_1_2'];

    public const ELECTRIC = ['A1_1_3'];

    /** Lorries (N2, N3) and buses (M2, M3). */
    public const HEAVY_CATEGORIES = ['N2', 'N3', 'M2', 'M3'];

    /** B4 components that are off-road equipment, and what each is. */
    public const OFFROAD_COMPONENTS = [
        'C9' => 'bull bar',
        'C13' => 'suplimentare pneumatică a suspensiei',
        'C25' => 'hardtop',
        'C30' => 'portbagaj cu scară',
        'C37' => 'suspensie pneumatică',
        'C40' => 'troliu',
        'C48' => 'cadru de protecție la răsturnare',
        'C56' => 'tuning suspensie',
        'C65' => 'suport roată de rezervă',
        'C82' => 'troliu de recuperare',
    ];

    /** @var array<string, array<string, int>> code => [service key => confidence] */
    private const SERVICES = [
        'A1' => ['general_repair' => 90],
        'A1_1' => ['engine' => 95],
        'A1_1_1' => ['engine' => 95, 'petrol' => 85, 'diesel' => 85],
        'A1_1_1_1' => ['fuel_injection' => 80],
        'A1_1_1_2' => ['fuel_injection' => 90],
        'A1_1_1_3' => ['fuel_injection' => 90],
        'A1_1_2' => ['hybrid' => 95],
        'A1_1_3' => ['electric_vehicle' => 95],
        'A1_1_4' => ['engine' => 80],
        'A1_1_4_1' => ['fuel_injection' => 75],
        'A1_1_4_2' => ['engine' => 80],
        'A1_1_4_3' => ['exhaust' => 90],
        'A1_1_4_4' => ['electrical' => 70],
        'A1_2' => ['transmission' => 95],
        'A1_2_1' => ['manual_transmission' => 95],
        'A1_2_1_1' => ['manual_transmission' => 95],
        'A1_2_1_2' => ['manual_transmission' => 95],
        'A1_2_1_3' => ['manual_transmission' => 90, '4x4_drivetrain' => 90],
        'A1_2_2' => ['automatic_transmission' => 95],
        'A1_2_2_1' => ['automatic_transmission' => 95],
        'A1_2_2_2' => ['automatic_transmission' => 95],
        'A1_2_2_3' => ['automatic_transmission' => 90, '4x4_drivetrain' => 90],
        'A1_2_3' => ['automatic_transmission' => 85],
        'A1_2_3_1' => ['automatic_transmission' => 85],
        'A1_2_3_2' => ['automatic_transmission' => 85],
        'A1_3' => ['suspension' => 85],
        'A1_3_1' => ['tyres' => 80, 'wheel_balancing' => 70],
        'A1_3_2' => ['axles' => 90],
        'A1_3_2_1' => ['axles' => 90],
        'A1_3_2_2' => ['axles' => 90],
        'A1_3_3' => ['suspension' => 95],
        'A1_3_3_1' => ['suspension' => 95],
        'A1_3_3_1_1' => ['suspension' => 95],
        'A1_3_3_1_2' => ['suspension' => 95],
        'A1_3_3_1_3' => ['suspension' => 95, 'electronics' => 60],
        'A1_3_3_2' => ['suspension' => 95],
        'A1_3_3_2_1' => ['suspension' => 95],
        'A1_3_3_2_2' => ['suspension' => 95, 'electronics' => 60],
        'A1_4' => ['steering' => 95],
        'A1_4_1' => ['steering' => 95],
        'A1_4_2' => ['steering' => 95],
        'A1_4_3' => ['steering' => 95, 'electronics' => 60],
        'A1_5' => ['brakes' => 95],
        'A1_5_1' => ['brakes' => 95],
        'A1_5_2' => ['brakes' => 95],
        'A1_5_2_1' => ['brakes' => 95],
        'A1_5_2_2' => ['brakes' => 95],
        'A1_5_2_3' => ['brakes' => 95, 'electronics' => 60],
        'A1_5_3' => ['brakes' => 95],
        'A1_5_3_1' => ['brakes' => 95],
        'A1_5_3_2' => ['brakes' => 95],
        'A1_5_4' => ['brakes' => 90],
        'A1_6' => ['electrical' => 95],
        'A1_6_1' => ['electrical' => 95],
        'A1_6_2' => ['electrical' => 90, 'electronics' => 90, 'diagnostics' => 80],
        'A1_6_2_1' => ['electronics' => 85],
        'A1_6_2_2' => ['electronics' => 85, 'lighting_installation' => 50],
        'A1_7' => ['bodywork' => 95],
        'A1_7_1' => ['bodywork' => 95],
        'A1_7_1_1' => ['bodywork' => 95],
        'A1_7_1_2' => ['bodywork' => 95, 'welding' => 70],
        'A1_7_1_3' => ['bodywork' => 95, 'chassis_repair' => 80],
        'A1_7_1_4' => ['bodywork' => 90],
        'A1_7_1_5' => ['rustproofing' => 95],
        'A1_7_2' => ['bodywork' => 95],
        'A1_7_2_1' => ['bodywork' => 95],
        'A1_7_2_2' => ['chassis_repair' => 95, 'welding' => 70],
        'A1_7_2_3' => ['chassis_repair' => 95],
        'A1_7_2_4' => ['chassis_repair' => 90],
        'A1_7_2_5' => ['rustproofing' => 95],
        'A1_7_3' => ['bodywork' => 80],
        'A1_8_1' => ['electronics' => 70],
        'A1_8_1_1' => ['electronics' => 60],
        'A1_8_1_2' => ['electronics' => 70],
        'A1_8_1_3' => ['electronics' => 70],
        'A1_8_2' => ['adas_calibration' => 90],
        'A1_8_2_1' => ['adas_calibration' => 90],
        'A1_8_2_2' => ['adas_calibration' => 90],
        'A1_8_2_3' => ['adas_calibration' => 90],
        'A1_8_2_4' => ['adas_calibration' => 80],
        'A1_8_3' => ['glass' => 70],
        'A1_8_4' => ['air_conditioning' => 95],
        'A1_8_4_1' => ['air_conditioning' => 95],
        'A1_8_4_2' => ['air_conditioning' => 90],
        'A2' => ['maintenance' => 95],
        'A2_1' => ['maintenance' => 95],
        'A2_2' => ['maintenance' => 95],
        'A2_3' => ['maintenance' => 90, 'brakes' => 80],
        'A2_4' => ['maintenance' => 95],
        'A2_5' => ['maintenance' => 90, 'electrical' => 60],
        'A2_6' => ['maintenance' => 95],
        'A2_7' => ['air_conditioning' => 85],
        'A3' => ['diagnostics' => 70],
        'A3_1' => ['wheel_alignment' => 95],
        'A3_2' => ['diagnostics' => 90, 'electronics' => 80],
        'B1_1' => ['engine' => 85],
        'B1_2' => ['transmission' => 85],
        'B1_3' => ['tyres' => 80, 'suspension' => 50],
        'B1_4' => ['steering' => 85],
        'B1_5' => ['brakes' => 85],
        'B1_6' => ['electrical' => 85],
        'B1_7' => ['bodywork' => 85],
        'B2_1' => ['engine' => 85],
        'B2_2' => ['transmission' => 85],
        'B2_3' => ['suspension' => 70],
        'B2_4' => ['steering' => 85],
        'B2_5' => ['brakes' => 85],
        'B2_6' => ['electrical' => 85],
        'B2_7' => ['bodywork' => 85],
        'B3_1' => ['exhaust' => 90, 'dpf' => 60],
        'B3_2' => ['accessories_installation' => 90],
        'B3_3' => ['accessories_installation' => 80],
        'B3_4' => ['air_conditioning' => 90],
        'B5' => ['dismantling' => 95],
        'B5_1' => ['dismantling' => 95],
        'B5_2' => ['dismantling' => 95],
        'B4_1_1' => ['vehicle_modifications' => 95, 'fabrication' => 70],
        'B4_1_2' => ['vehicle_modifications' => 95],
        'B4_1_3' => ['vehicle_modifications' => 95, 'accessories_installation' => 80],
        'B4_2' => ['vehicle_modifications' => 95, 'fabrication' => 80],
    ];

    /** @var array<string, array<string, int>> B4 component => [service key => confidence] */
    private const B4_COMPONENTS = [
        'C1' => ['electrical' => 70, 'accessories_installation' => 70],
        'C9' => ['offroad_modifications' => 90, 'accessories_installation' => 90],
        'C11' => ['towbar_installation' => 90],
        'C13' => ['suspension' => 80],
        'C25' => ['offroad_modifications' => 70, 'accessories_installation' => 80],
        'C30' => ['accessories_installation' => 80],
        'C34' => ['air_conditioning' => 85],
        'C35' => ['accessories_installation' => 70],
        'C37' => ['suspension' => 90],
        'C38' => ['transmission' => 70],
        'C40' => ['winch_installation' => 95, 'offroad_modifications' => 85],
        'C41' => ['engine' => 60],
        'C48' => ['fabrication' => 80, 'offroad_modifications' => 80],
        'C53' => ['engine' => 60],
        'C54' => ['lighting_installation' => 95],
        'C55' => ['brakes' => 80],
        'C56' => ['suspension' => 90, 'offroad_suspension' => 75],
        'C59' => ['tyres' => 60],
        'C65' => ['accessories_installation' => 80, 'offroad_modifications' => 60],
        'C69' => ['gnc' => 90],
        'C70' => ['gpl' => 90],
        'C71' => ['electric_vehicle' => 80],
        'C74' => ['transmission' => 60],
        'C82' => ['winch_installation' => 95, 'offroad_modifications' => 85],
        'C85' => ['steering' => 80],
    ];

    /** @return array<string, int> service key => confidence */
    public function servicesForCode(string $system, string $code): array
    {
        return match (strtoupper($system)) {
            'ITP' => ['itp' => 100],
            'GPL' => str_ends_with($code, '_GNC') ? ['gnc' => 100] : ['gpl' => 100],
            'TLV' => ['tachograph' => 100],
            'B4' => preg_match('/_(C\d+)$/', $code, $match) === 1
                ? (self::B4_COMPONENTS[$match[1]] ?? ['vehicle_modifications' => 90])
                : (self::SERVICES[$code] ?? ['vehicle_modifications' => 90]),
            default => self::SERVICES[$code] ?? [],
        };
    }

    /**
     * What a workshop's current authorisations support, each service with the RAR codes behind it.
     * Vehicle categories add their own services: N2/N3 lorries, M2/M3 buses and L motorcycles
     * on a repair activity are authorisations to work on those vehicles.
     *
     * @param  iterable<WorkshopAuthorization>  $authorizations  with activities loaded
     * @return array<string, array{confidence: int, evidence: list<array<string, mixed>>, source_record_id: int|null}>
     */
    public function servicesFor(iterable $authorizations): array
    {
        $services = [];

        foreach ($authorizations as $authorization) {
            foreach ($authorization->activities as $activity) {
                $found = $this->servicesForCode($authorization->system, $activity->code);

                if ($authorization->system === 'SERVICE' && preg_match('/^(A1|A2|B1|B2)/', $activity->code) === 1) {
                    $categories = (array) ($activity->vehicle_categories ?? []);

                    if (array_intersect($categories, ['N2', 'N3']) !== []) {
                        $found['truck_service'] = max($found['truck_service'] ?? 0, 90);
                    }

                    if (array_intersect($categories, self::HEAVY_CATEGORIES) !== []) {
                        $found['commercial_vehicle_service'] = max($found['commercial_vehicle_service'] ?? 0, 85);
                    }

                    if (preg_grep('/^L\d/', $categories) !== []) {
                        $found['motorcycle_service'] = max($found['motorcycle_service'] ?? 0, 85);
                    }
                }

                foreach ($found as $key => $confidence) {
                    $service = $services[$key] ?? ['confidence' => 0, 'evidence' => [], 'source_record_id' => $authorization->source_record_id];
                    $service['confidence'] = max($service['confidence'], $confidence);

                    if (count($service['evidence']) < 12) {
                        $service['evidence'][] = [
                            'system' => $authorization->system,
                            'code' => $activity->display_code,
                            'description' => $activity->description,
                            'authorization' => $authorization->authorization_number,
                            'source_record_id' => $authorization->source_record_id,
                        ];
                    }

                    $services[$key] = $service;
                }
            }
        }

        return $services;
    }
}
