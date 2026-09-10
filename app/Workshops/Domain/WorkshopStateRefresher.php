<?php

namespace App\Workshops\Domain;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Workshops\Classification\CapabilityAssessor;
use App\Workshops\Sources\Rar\RarActivityMap;

/**
 * Recomputes everything about a workshop that is derived from its sources: which RAR registries
 * list it, the services RAR authorises, its capabilities, its primary contacts, whether it is
 * active and how confident we are in it.
 *
 * Called after any source record of the workshop changes, including when one stops being current,
 * so a withdrawn authorisation takes its services and flags with it.
 */
class WorkshopStateRefresher
{
    public function __construct(
        private RarActivityMap $activityMap,
        private WorkshopServiceWriter $services,
        private CapabilityAssessor $capabilities,
        private ContactWriter $contacts,
        private ConfidenceScorer $confidence,
    ) {}

    public function refresh(Workshop $workshop): void
    {
        $authorizations = $workshop->authorizations()->where('is_current', true)->with('activities')->get();
        $systems = $authorizations->pluck('system')->unique();
        $serviceCodes = $authorizations->where('system', 'SERVICE')->flatMap(fn ($authorization) => $authorization->activities->pluck('code'));

        $workshop->forceFill([
            'is_rar_authorized' => $systems->contains('SERVICE'),
            'is_itp' => $systems->contains('ITP'),
            'is_gpl_gnc' => $systems->contains('GPL'),
            'is_tlv' => $systems->contains('TLV'),
            'is_modification_authorized' => $systems->contains('B4'),
            'is_dismantling' => $serviceCodes->contains(fn (string $code): bool => str_starts_with($code, 'B5')),
        ]);

        $this->services->sync($workshop, WorkshopEvidenceType::RarAuthorization, $this->activityMap->servicesFor($authorizations));
        $this->capabilities->assess($workshop);
        $this->contacts->electPrimaries($workshop);

        $workshop->is_active = $workshop->merged_into_id === null
            && $workshop->sourceLinks()->whereHas('sourceRecord', fn ($query) => $query->where('is_current', true))->exists();
        $workshop->confidence_score = $this->confidence->score($workshop);
        $workshop->save();
    }
}
