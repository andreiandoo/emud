<?php

namespace App\Workshops\Classification;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Models\WorkshopCapability;
use App\Models\WorkshopService;
use App\Workshops\Sources\Rar\RarActivityMap;
use Illuminate\Support\Collection;

/**
 * What a workshop can work on: 4x4 drivetrains, electric and hybrid drives, lorries, and how
 * relevant it is to an off-road shop.
 *
 * Built only from evidence. RAR authorisations are the strongest; a website or a map tag counts
 * for less; a company name counts for nothing ("4X4 SERVICE SRL" proves only that someone liked
 * the name). A capability with no evidence either way stays null (unknown), which is different
 * from false: false means RAR authorises related work on this site but not this.
 *
 * Every result keeps the codes or phrases it rests on, so the admin can show why.
 */
class CapabilityAssessor
{
    /** Services only a workshop that works on off-road vehicles tends to advertise. */
    private const OFFROAD_SERVICES = [
        'offroad_suspension', 'offroad_modifications', 'winch_installation', 'snorkel_installation',
        '4x4_drivetrain', 'transfer_case', 'differential',
    ];

    public function assess(Workshop $workshop): void
    {
        $activities = $this->activities($workshop);
        $service = $activities->where('system', 'SERVICE');
        $claims = $this->claims($workshop);

        $results = [
            '4x4' => $this->fourByFour($service, $claims),
            'awd_permanent' => $this->permanentAllWheelDrive($service),
            'ev' => $this->drive($service, $activities, $claims, RarActivityMap::ELECTRIC, 'electric_vehicle', 'C71'),
            // RAR had not granted A1.1.2 (hybrid engines) to a single workshop when the registry was
            // first read (2026-09-10), so its absence says nothing about a workshop and is not a "no".
            'hybrid' => $this->drive($service, $activities, $claims, RarActivityMap::HYBRID, 'hybrid', absenceIsAnswer: false),
            'trucks' => $this->trucks($service, $claims),
            'itp_4x4' => $this->itpAllWheelDrive($activities->where('system', 'ITP')),
            'offroad' => $this->offroad($service, $activities, $claims),
        ];

        foreach ($results as $capability => $result) {
            if ($result === null) {
                WorkshopCapability::query()->where('workshop_id', $workshop->id)->where('capability', $capability)->delete();

                continue;
            }

            WorkshopCapability::query()->updateOrCreate(
                ['workshop_id' => $workshop->id, 'capability' => $capability],
                ['value' => $result['value'], 'score' => $result['score'], 'basis' => $result['basis'], 'evidence' => $result['evidence']],
            );
        }

        $workshop->forceFill([
            'supports_4x4' => $results['4x4']['value'] ?? null,
            'supports_ev' => $results['ev']['value'] ?? null,
            'supports_hybrid' => $results['hybrid']['value'] ?? null,
            'supports_trucks' => $results['trucks']['value'] ?? null,
            'offroad_score' => $results['offroad']['score'] ?? null,
        ]);
    }

    private function fourByFour(Collection $service, Collection $claims): ?array
    {
        $permanent = $service->whereIn('code', RarActivityMap::PERMANENT_ALL_WHEEL_DRIVE);
        $multiAxle = $service->whereIn('code', RarActivityMap::MULTI_AXLE_TRANSMISSION);
        $advertised = $this->advertised($claims, ['4x4_drivetrain', 'transfer_case']);
        $bonus = $advertised === [] ? 0 : 10;

        if ($permanent->isNotEmpty()) {
            return $this->result(true, 85 + $bonus, 'rar_authorization', [...$this->codes($permanent), ...$advertised]);
        }

        if ($multiAxle->isNotEmpty()) {
            return $this->result(true, 65 + $bonus, 'rar_authorization', [...$this->codes($multiAxle), ...$advertised]);
        }

        if ($advertised !== []) {
            return $this->result(true, 60, $advertised[0]['basis'], $advertised);
        }

        $transmission = $service->filter(fn (array $activity): bool => str_starts_with($activity['code'], 'A1_2'));

        if ($transmission->isNotEmpty()) {
            return $this->result(false, 60, 'rar_authorization', [[
                'basis' => 'rar_authorization',
                'text' => 'RAR autorizează aici reparații de transmisie, dar niciun cod pentru tracțiune pe mai multe axe.',
                'codes' => $transmission->pluck('display')->unique()->values()->all(),
            ]]);
        }

        return null;
    }

    private function permanentAllWheelDrive(Collection $service): ?array
    {
        $permanent = $service->whereIn('code', RarActivityMap::PERMANENT_ALL_WHEEL_DRIVE);

        if ($permanent->isNotEmpty()) {
            return $this->result(true, 90, 'rar_authorization', $this->codes($permanent));
        }

        return $service->contains(fn (array $activity): bool => str_starts_with($activity['code'], 'A1_2'))
            ? $this->result(false, 60, 'rar_authorization', [])
            : null;
    }

    /** Electric or hybrid drive: RAR's engine codes first, then a homologated conversion, then claims. */
    private function drive(Collection $service, Collection $activities, Collection $claims, array $codes, string $serviceKey, ?string $b4Component = null, bool $absenceIsAnswer = true): ?array
    {
        $authorized = $service->whereIn('code', $codes);

        if ($authorized->isNotEmpty()) {
            return $this->result(true, 95, 'rar_authorization', $this->codes($authorized));
        }

        if ($b4Component !== null) {
            $conversion = $activities->filter(fn (array $activity): bool => $activity['system'] === 'B4' && str_ends_with($activity['code'], '_'.$b4Component));

            if ($conversion->isNotEmpty()) {
                return $this->result(true, 70, 'rar_authorization', $this->codes($conversion));
            }
        }

        $advertised = $this->advertised($claims, [$serviceKey]);

        if ($advertised !== []) {
            return $this->result(true, 60, $advertised[0]['basis'], $advertised);
        }

        $engine = $service->filter(fn (array $activity): bool => str_starts_with($activity['code'], 'A1_1'));

        return $absenceIsAnswer && $engine->isNotEmpty()
            ? $this->result(false, 60, 'rar_authorization', [[
                'basis' => 'rar_authorization',
                'text' => 'RAR autorizează aici lucrări la motor, fără acest tip de propulsie.',
                'codes' => $engine->pluck('display')->unique()->take(6)->values()->all(),
            ]])
            : null;
    }

    private function trucks(Collection $service, Collection $claims): ?array
    {
        $repair = $service->filter(fn (array $activity): bool => preg_match('/^(A1|A2|B1|B2)/', $activity['code']) === 1);
        $heavy = $repair->filter(fn (array $activity): bool => array_intersect($activity['categories'], RarActivityMap::HEAVY_CATEGORIES) !== []);

        if ($heavy->isNotEmpty()) {
            $categories = $heavy->flatMap(fn (array $activity): array => array_values(array_intersect($activity['categories'], RarActivityMap::HEAVY_CATEGORIES)))->unique()->sort()->values()->all();

            return $this->result(true, 85, 'rar_authorization', [[
                'basis' => 'rar_authorization',
                'text' => 'Categorii de vehicule autorizate: '.implode(', ', $categories),
                'codes' => $heavy->pluck('display')->unique()->take(6)->values()->all(),
            ]]);
        }

        $advertised = $this->advertised($claims, ['truck_service']);

        if ($advertised !== []) {
            return $this->result(true, 60, $advertised[0]['basis'], $advertised);
        }

        return $repair->isNotEmpty() ? $this->result(false, 60, 'rar_authorization', []) : null;
    }

    /**
     * Whether an ITP station may inspect permanent four-wheel-drive vehicles. Most can; the ones
     * that cannot say so in an explicit RAR interdiction.
     */
    private function itpAllWheelDrive(Collection $itp): ?array
    {
        $classes = $itp->filter(fn (array $activity): bool => in_array($activity['code'], ['ITP_CLASS_2', 'ITP_CLASS_3'], true));

        if ($classes->isEmpty()) {
            return null;
        }

        $interdictions = $classes->flatMap(fn (array $activity): array => $activity['restrictions'])
            ->filter(fn (array $restriction): bool => str_starts_with((string) ($restriction['code'] ?? ''), 'ITP_INTERDICTION_AUTO_PERMANENT_ALLWHEEL'));

        return $interdictions->isNotEmpty()
            ? $this->result(false, 90, 'rar_authorization', $interdictions->map(fn (array $restriction): array => ['basis' => 'rar_authorization', 'text' => $restriction['text'], 'code' => $restriction['code']])->values()->all())
            : $this->result(true, 80, 'rar_authorization', [['basis' => 'rar_authorization', 'text' => 'Stație ITP clasa II/III fără interdicție pentru tracțiune integrală permanentă.']]);
    }

    /**
     * 0 to 100: how useful this workshop is to someone fitting off-road parts. The RAR signals
     * are what a 4x4 needs done (multi-axle drive, rigid axles, suspension, steering geometry,
     * body-on-frame chassis work); the strongest signals are RAR permission to fit homologated
     * off-road equipment and the workshop saying so itself.
     */
    private function offroad(Collection $service, Collection $activities, Collection $claims): ?array
    {
        $score = 0;
        $evidence = [];
        $codes = $service->pluck('code')->all();
        $has = fn (string ...$wanted): bool => array_intersect($wanted, $codes) !== [];
        $startsWith = fn (string $prefix): bool => $service->contains(fn (array $activity): bool => str_starts_with($activity['code'], $prefix));

        $signals = [
            [$has(...RarActivityMap::PERMANENT_ALL_WHEEL_DRIVE), 25, 'tracțiune integrală permanentă (A1.2.1.3 / A1.2.2.3)'],
            [! $has(...RarActivityMap::PERMANENT_ALL_WHEEL_DRIVE) && $has(...RarActivityMap::MULTI_AXLE_TRANSMISSION), 15, 'transmisie cu tracțiune pe mai multe axe'],
            [$has('A1_3_2_1'), 10, 'punți rigide (A1.3.2.1)'],
            [$startsWith('A1_3_3_1'), 5, 'suspensie mecanică / mecano-hidraulică'],
            [$startsWith('A1_4'), 5, 'direcție'],
            [$has('A3_1'), 5, 'geometrie direcție (A3.1)'],
            [$has('A1_7_2_2', 'A1_7_2_3'), 5, 'reparare / redresare șasiu'],
        ];

        foreach ($signals as [$present, $points, $label]) {
            if ($present) {
                $score += $points;
                $evidence[] = ['basis' => 'rar_authorization', 'text' => $label, 'points' => $points];
            }
        }

        // Nearly every workshop is authorised for N1; it only adds to signals that are there.
        if ($score > 0 && $service->contains(fn (array $activity): bool => in_array('N1', $activity['categories'], true))) {
            $score += 5;
            $evidence[] = ['basis' => 'rar_authorization', 'text' => 'categoria N1 (pick-up, utilitare ușoare)', 'points' => 5];
        }

        $components = $activities->filter(fn (array $activity): bool => $activity['system'] === 'B4' && preg_match('/_(C\d+)$/', $activity['code'], $m) === 1 && isset(RarActivityMap::OFFROAD_COMPONENTS[$m[1]]));
        $specialist = false;

        if ($components->isNotEmpty()) {
            $points = min(30, 15 + 5 * ($components->count() - 1));
            $score += $points;
            $specialist = true;
            $evidence[] = ['basis' => 'rar_authorization', 'text' => 'Autorizat RAR B4 pentru echipamente off-road', 'codes' => $components->pluck('display')->values()->all(), 'points' => $points];
        }

        $advertised = $this->advertised($claims, self::OFFROAD_SERVICES);

        if ($advertised !== []) {
            $points = min(35, 25 + 5 * (count($advertised) - 1));
            $score += $points;
            $specialist = true;
            $evidence[] = ['basis' => $advertised[0]['basis'], 'text' => 'Servicii off-road declarate', 'claims' => $advertised, 'points' => $points];
        }

        if ($score === 0) {
            return null;
        }

        $score = min(100, $score);

        return $this->result($score >= 60 && $specialist ? true : null, $score, $specialist && $components->isEmpty() ? $advertised[0]['basis'] : 'rar_authorization', $evidence);
    }

    /**
     * Every current RAR activity of the workshop, flattened.
     *
     * @return Collection<int, array{system: string, code: string, display: string, description: string|null, categories: list<string>, restrictions: list<array>}>
     */
    private function activities(Workshop $workshop): Collection
    {
        return $workshop->authorizations()->where('is_current', true)->with('activities')->get()
            ->flatMap(fn ($authorization) => $authorization->activities->map(fn ($activity): array => [
                'system' => $authorization->system,
                'code' => $activity->code,
                'display' => $activity->display_code,
                'description' => $activity->description,
                'categories' => array_values((array) ($activity->vehicle_categories ?? [])),
                'restrictions' => array_values(array_filter((array) ($activity->restrictions ?? []), 'is_array')),
            ]))
            ->values();
    }

    /** Services the workshop is said to offer by anything other than RAR, grouped by key. */
    private function claims(Workshop $workshop): Collection
    {
        return WorkshopService::query()
            ->with('serviceType:id,key')
            ->where('workshop_id', $workshop->id)
            ->where('evidence_type', '!=', WorkshopEvidenceType::RarAuthorization->value)
            ->get()
            ->groupBy(fn (WorkshopService $service): string => (string) $service->serviceType?->key);
    }

    /** @return list<array{basis: string, service: string, confidence: int, evidence: array}> */
    private function advertised(Collection $claims, array $keys): array
    {
        $found = [];

        foreach ($keys as $key) {
            foreach ($claims->get($key, collect()) as $service) {
                $found[] = [
                    'basis' => $service->evidence_type->value,
                    'service' => $key,
                    'confidence' => $service->confidence_score,
                    'evidence' => array_slice((array) $service->evidence, 0, 3),
                ];
            }
        }

        usort($found, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return $found;
    }

    /** @return list<array{basis: string, code: string, text: string|null}> */
    private function codes(Collection $activities): array
    {
        return $activities->unique('code')->map(fn (array $activity): array => [
            'basis' => 'rar_authorization',
            'code' => $activity['display'],
            'text' => $activity['description'],
        ])->values()->all();
    }

    private function result(?bool $value, int $score, string $basis, array $evidence): array
    {
        return ['value' => $value, 'score' => max(0, min(100, $score)), 'basis' => $basis, 'evidence' => array_values($evidence)];
    }
}
