<?php

namespace App\Workshops\Domain;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Models\WorkshopService;
use App\Workshops\Classification\ServiceTaxonomy;

/**
 * Writes one kind of evidence for a workshop's services at a time.
 *
 * sync() makes the rows of one evidence type match the given set exactly, and never touches the
 * rows of another type: re-reading RAR must not erase what the website said, and a website that
 * stops mentioning winches must not remove anything RAR authorised.
 */
class WorkshopServiceWriter
{
    public function __construct(private ServiceTaxonomy $taxonomy) {}

    /**
     * @param  array<string, array{confidence: int, evidence?: list<array<string, mixed>>, source_record_id?: int|null, is_authorized?: bool|null}>  $services
     */
    public function sync(Workshop $workshop, WorkshopEvidenceType $type, array $services): void
    {
        $now = now();
        $rows = [];

        foreach ($services as $key => $service) {
            $rows[] = [
                'workshop_id' => $workshop->id,
                'service_type_id' => $this->taxonomy->idFor($key),
                'evidence_type' => $type->value,
                'source_record_id' => $service['source_record_id'] ?? null,
                'evidence' => json_encode(array_values($service['evidence'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                // Only RAR authorises. A website or a map tag says a service is offered, which is
                // a different claim and is never recorded as an authorisation.
                'is_authorized' => $type === WorkshopEvidenceType::RarAuthorization ? true : null,
                'confidence_score' => max(0, min(100, (int) $service['confidence'])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            WorkshopService::query()->upsert(
                $rows,
                ['workshop_id', 'service_type_id', 'evidence_type'],
                ['source_record_id', 'evidence', 'is_authorized', 'confidence_score', 'updated_at'],
            );
        }

        WorkshopService::query()
            ->where('workshop_id', $workshop->id)
            ->where('evidence_type', $type->value)
            ->whereNotIn('service_type_id', array_column($rows, 'service_type_id') ?: [0])
            ->delete();
    }
}
