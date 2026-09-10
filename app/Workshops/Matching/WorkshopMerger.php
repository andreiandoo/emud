<?php

namespace App\Workshops\Matching;

use App\Enums\WorkshopMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopAuthorization;
use App\Models\WorkshopCapability;
use App\Models\WorkshopContact;
use App\Models\WorkshopMatchCandidate;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopService;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Domain\WorkshopStateRefresher;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folds one workshop into another without losing where anything came from.
 *
 * Source links, authorisations, contacts, services and website candidates move to the survivor;
 * a row is deleted only when the survivor already holds the identical row from the same source,
 * and then its dates and confidence are kept on the survivor's copy. The duplicate itself is never
 * deleted: it stays, inactive, pointing at the workshop it became part of.
 */
class WorkshopMerger
{
    public function __construct(private WorkshopStateRefresher $state) {}

    /**
     * The workshop that survives a merge: the one with a RAR authorisation, then the one tied to a
     * company, then the more trusted, then the older.
     *
     * @return array{0: Workshop, 1: Workshop} [survivor, duplicate]
     */
    public static function order(Workshop $a, Workshop $b): array
    {
        $rank = fn (Workshop $workshop): array => [
            (int) $workshop->is_rar_authorized,
            (int) ($workshop->company_id !== null),
            (int) $workshop->confidence_score,
            -$workshop->id,
        ];

        return $rank($a) >= $rank($b) ? [$a, $b] : [$b, $a];
    }

    public function merge(Workshop $survivor, Workshop $duplicate, array $evidence = [], ?int $reviewerId = null, int $score = 100): Workshop
    {
        if ($survivor->is($duplicate) || $duplicate->merged_into_id !== null) {
            throw new InvalidArgumentException('A workshop cannot be merged into itself or merged twice.');
        }

        return DB::transaction(function () use ($survivor, $duplicate, $evidence, $reviewerId, $score): Workshop {
            WorkshopSourceLink::query()->where('workshop_id', $duplicate->id)->update(['workshop_id' => $survivor->id, 'updated_at' => now()]);
            WorkshopAuthorization::query()->where('workshop_id', $duplicate->id)->update(['workshop_id' => $survivor->id, 'updated_at' => now()]);
            WorkshopRecordMatch::query()->where('target_type', WorkshopRecordMatch::TARGET_WORKSHOP)->where('target_id', $duplicate->id)->update(['target_id' => $survivor->id]);

            foreach (WorkshopContact::query()->where('workshop_id', $duplicate->id)->get() as $contact) {
                $same = WorkshopContact::query()
                    ->where('workshop_id', $survivor->id)
                    ->where('type', $contact->type)
                    ->where('normalized_value', $contact->normalized_value)
                    ->where('data_source_id', $contact->data_source_id)
                    ->first();

                if ($same === null) {
                    $contact->forceFill(['workshop_id' => $survivor->id, 'is_primary' => false])->save();

                    continue;
                }

                $same->forceFill([
                    'confidence_score' => max($same->confidence_score, $contact->confidence_score),
                    'first_seen_at' => min($same->first_seen_at, $contact->first_seen_at),
                    'last_seen_at' => max($same->last_seen_at, $contact->last_seen_at),
                ])->save();
                $contact->delete();
            }

            foreach (WorkshopService::query()->where('workshop_id', $duplicate->id)->get() as $service) {
                $same = WorkshopService::query()
                    ->where('workshop_id', $survivor->id)
                    ->where('service_type_id', $service->service_type_id)
                    ->where('evidence_type', $service->evidence_type)
                    ->first();

                if ($same === null) {
                    $service->forceFill(['workshop_id' => $survivor->id])->save();

                    continue;
                }

                if ($service->confidence_score > $same->confidence_score) {
                    $same->forceFill(['confidence_score' => $service->confidence_score, 'evidence' => array_merge((array) $same->evidence, (array) $service->evidence)])->save();
                }

                $service->delete();
            }

            foreach (WorkshopWebsiteCandidate::query()->where('workshop_id', $duplicate->id)->get() as $site) {
                $same = WorkshopWebsiteCandidate::query()->where('workshop_id', $survivor->id)->where('domain', $site->domain)->first();

                if ($same === null) {
                    $site->forceFill(['workshop_id' => $survivor->id])->save();
                } else {
                    if ($site->validation_status === WorkshopWebsiteCandidate::ACCEPTED && $same->validation_status !== WorkshopWebsiteCandidate::ACCEPTED) {
                        $same->forceFill(['validation_status' => WorkshopWebsiteCandidate::ACCEPTED, 'confidence' => max($same->confidence, $site->confidence)])->save();
                    }

                    $site->delete();
                }
            }

            WorkshopCapability::query()->where('workshop_id', $duplicate->id)->delete();

            foreach (['company_id', 'address', 'normalized_address', 'street', 'street_number', 'locality', 'normalized_locality', 'county', 'county_code', 'postal_code'] as $field) {
                if ($survivor->{$field} === null && $duplicate->{$field} !== null) {
                    $survivor->{$field} = $duplicate->{$field};
                }
            }

            if ((int) $duplicate->coordinates_confidence > (int) $survivor->coordinates_confidence) {
                foreach (['latitude', 'longitude', 'coordinates_source', 'coordinates_confidence', 'geocode_status'] as $field) {
                    $survivor->{$field} = $duplicate->{$field};
                }
            }

            $survivor->workstations = max((int) $survivor->workstations, (int) $duplicate->workstations) ?: null;
            $survivor->employees = max((int) $survivor->employees, (int) $duplicate->employees) ?: null;
            $survivor->first_seen_at = $survivor->first_seen_at === null || ($duplicate->first_seen_at !== null && $duplicate->first_seen_at->lessThan($survivor->first_seen_at)) ? $duplicate->first_seen_at : $survivor->first_seen_at;
            $survivor->save();

            $duplicate->forceFill(['merged_into_id' => $survivor->id, 'is_active' => false])->save();

            WorkshopMatchCandidate::query()->updateOrCreate(
                ['workshop_a_id' => min($survivor->id, $duplicate->id), 'workshop_b_id' => max($survivor->id, $duplicate->id)],
                [
                    'score' => max(0, min(100, $score)),
                    'evidence' => $evidence + ['survivor_id' => $survivor->id],
                    'status' => $reviewerId !== null ? WorkshopMatchStatus::Confirmed : WorkshopMatchStatus::AutoMerged,
                    'reviewed_by' => $reviewerId,
                    'reviewed_at' => $reviewerId !== null ? now() : null,
                ],
            );

            $survivor = $survivor->fresh();
            $this->state->refresh($survivor);

            return $survivor;
        });
    }
}
