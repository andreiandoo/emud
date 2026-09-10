<?php

namespace App\Workshops\Sources\Osm;

use App\Enums\WorkshopEvidenceType;
use App\Enums\WorkshopRecordMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopSourceRecord;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Contracts\SourceRecordNormalizer;
use App\Workshops\Domain\ContactWriter;
use App\Workshops\Domain\WorkshopServiceWriter;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Matching\LocalityCountyResolver;
use App\Workshops\Matching\MatchDecision;
use App\Workshops\Matching\RecordMatchRecorder;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\RomanianCounties;
use App\Workshops\Support\TextNormalizer;
use App\Workshops\Web\DirectoryDomains;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies one OpenStreetMap workshop: to the registry workshop it matches, or as a new workshop
 * when it matches nothing, since a mapped workshop is evidence of a real place. A contested match
 * is left for review and changes nothing.
 *
 * What OSM contributes is kept as OSM's: its point (usually the best one we have), contacts
 * attributed to the OSM record, and services as "OSM tagged".
 */
class OsmRecordNormalizer implements SourceRecordNormalizer
{
    public function __construct(
        private OsmPoiParser $parser,
        private OsmWorkshopMatcher $matcher,
        private RecordMatchRecorder $matches,
        private ContactWriter $contacts,
        private WorkshopServiceWriter $services,
        private WorkshopStateRefresher $state,
        private LocalityCountyResolver $counties,
    ) {}

    public function normalize(WorkshopSourceRecord $record): void
    {
        $poi = $this->parser->parse((array) $record->payload);

        DB::transaction(function () use ($record, $poi): void {
            $link = WorkshopSourceLink::query()->with('workshop')->where('source_record_id', $record->id)->first();
            $created = false;

            if ($link?->workshop !== null) {
                $workshop = $link->workshop;

                while ($workshop->merged_into_id !== null && $workshop->mergedInto !== null) {
                    $workshop = $workshop->mergedInto;
                }

                [$matchType, $confidence] = [$link->match_type, $link->match_confidence];
            } else {
                $reviewed = $this->matches->reviewed($record, WorkshopRecordMatch::TARGET_WORKSHOP);
                $decision = $reviewed !== null
                    ? new MatchDecision($reviewed->status, $reviewed->target_id, $reviewed->method ?? 'reviewed', $reviewed->score ?? 100)
                    : $this->matcher->match($poi);

                if ($reviewed === null) {
                    $this->matches->record($record, WorkshopRecordMatch::TARGET_WORKSHOP, $decision);
                }

                if ($decision->isLinked() && ($target = Workshop::query()->find($decision->targetId)) !== null) {
                    [$workshop, $matchType, $confidence] = [$target, 'osm_'.$decision->method, (int) $decision->score];
                } elseif (in_array($decision->status, [WorkshopRecordMatchStatus::Unmatched, WorkshopRecordMatchStatus::Rejected], true) && $poi->name !== null) {
                    [$workshop, $matchType, $confidence] = [$this->create($poi), 'osm_poi', 100];
                    $created = true;
                } else {
                    // Probable or ambiguous, or an unnamed point: nothing to attach it to yet.
                    $record->markParsed();

                    return;
                }
            }

            WorkshopSourceLink::query()->updateOrCreate(
                ['source_record_id' => $record->id],
                ['workshop_id' => $workshop->id, 'data_source_id' => $record->data_source_id, 'external_id' => $record->external_id, 'match_type' => $matchType, 'match_confidence' => min(100, $confidence), 'evidence' => ['osm' => $poi->reference()]],
            );

            $this->apply($workshop, $poi, $record, $created);
            $this->state->refresh($workshop);
            $record->markParsed();
        });
    }

    private function create(OsmPoi $poi): Workshop
    {
        [$county] = $poi->location->countyCode !== null ? [$poi->location->countyCode] : $this->counties->resolve($poi->location->locality, $poi->latitude, $poi->longitude);
        $name = (string) $poi->name;

        $workshop = new Workshop([
            'name' => $name,
            'normalized_name' => CompanyNameNormalizer::normalize($name),
            'slug' => Str::limit(Str::slug($name.' '.$poi->location->locality), 180, ''),
            'address' => $poi->location->address,
            'normalized_address' => $poi->location->address !== null ? AddressNormalizer::normalize($poi->location->address) : null,
            'street' => $poi->location->street,
            'street_number' => $poi->location->streetNumber,
            'locality' => $poi->location->locality,
            'normalized_locality' => TextNormalizer::fold($poi->location->locality) ?: null,
            'county_code' => $county,
            'county' => $county !== null ? RomanianCounties::name($county) : null,
            'postal_code' => $poi->location->postalCode,
            'first_seen_at' => now(),
        ]);
        $workshop->save();

        return $workshop;
    }

    private function apply(Workshop $workshop, OsmPoi $poi, WorkshopSourceRecord $record, bool $created): void
    {
        $confidence = $poi->location->coordinateConfidence;

        if ($poi->hasCoordinates() && $confidence > (int) $workshop->coordinates_confidence) {
            $workshop->forceFill([
                'latitude' => $poi->latitude,
                'longitude' => $poi->longitude,
                'coordinates_source' => 'osm',
                'coordinates_confidence' => $confidence,
                'geocode_status' => 'located',
            ]);
        }

        if (! $created && $workshop->postal_code === null && $poi->location->postalCode !== null) {
            $workshop->postal_code = $poi->location->postalCode;
        }

        $workshop->last_seen_at = $record->last_seen_at;
        $workshop->save();

        $label = 'OpenStreetMap';

        foreach ($poi->phones as $phone) {
            $this->contacts->phone($workshop, $phone, $record, 70, $label, $poi->url());
        }

        foreach ($poi->emails as $email) {
            $this->contacts->email($workshop, $email, $record, 70, $label, $poi->url());
        }

        foreach ($poi->websites as $url) {
            $domain = DirectoryDomains::host($url);

            if ($domain === null) {
                continue;
            }

            if (DirectoryDomains::isSocial($domain)) {
                $this->contacts->link($workshop, DirectoryDomains::socialType($domain), $url, $record, 60, $label, $poi->url());

                continue;
            }

            $this->contacts->link($workshop, 'website', $url, $record, 65, $label, $poi->url());

            if (! DirectoryDomains::isDirectory($domain)) {
                $candidate = WorkshopWebsiteCandidate::query()->firstOrNew(['workshop_id' => $workshop->id, 'domain' => $domain]);
                $candidate->fill([
                    'url' => $candidate->url ?? $url,
                    'discovered_via' => $candidate->discovered_via ?? 'osm',
                    'confidence' => max((int) $candidate->confidence, 70),
                    'evidence' => array_merge((array) $candidate->evidence, ['osm' => $poi->reference()]),
                ]);
                $candidate->validation_status ??= WorkshopWebsiteCandidate::PENDING;
                $candidate->save();
            }
        }

        if ($poi->facebook !== null) {
            $this->contacts->link($workshop, 'facebook', $poi->facebook, $record, 60, $label, $poi->url());
        }

        if ($poi->instagram !== null) {
            $this->contacts->link($workshop, 'instagram', $poi->instagram, $record, 60, $label, $poi->url());
        }

        $this->services->sync($workshop, WorkshopEvidenceType::Osm, $this->servicesFromAllOsmRecords($workshop));
    }

    /**
     * A workshop can be mapped twice (a node and its building); the OSM services are the union of
     * every OSM record linked to it.
     *
     * @return array<string, array{confidence: int, evidence: list<array<string, mixed>>, source_record_id: int}>
     */
    private function servicesFromAllOsmRecords(Workshop $workshop): array
    {
        $services = [];
        $records = WorkshopSourceRecord::query()
            ->whereIn('id', WorkshopSourceLink::query()->select('source_record_id')->where('workshop_id', $workshop->id))
            ->where('record_type', 'poi')
            ->where('is_current', true)
            ->get();

        foreach ($records as $osmRecord) {
            $poi = $this->parser->parse((array) $osmRecord->payload);

            foreach ($poi->services as $key => $confidence) {
                $service = $services[$key] ?? ['confidence' => 0, 'evidence' => [], 'source_record_id' => $osmRecord->id];
                $service['confidence'] = max($service['confidence'], $confidence);
                $service['evidence'][] = ['osm' => $poi->reference(), 'tags' => array_intersect_key($poi->tags, array_flip(array_filter(array_keys($poi->tags), fn (string $tag): bool => str_starts_with($tag, 'service:') || in_array($tag, ['shop', 'craft', 'amenity'], true))))];
                $services[$key] = $service;
            }
        }

        return $services;
    }
}
