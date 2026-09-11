<?php

namespace App\Livewire\Admin\Workshops;

use App\Enums\WorkshopMatchStatus;
use App\Enums\WorkshopRecordMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopCompany;
use App\Models\WorkshopMatchCandidate;
use App\Models\WorkshopRecordMatch;
use App\Workshops\Data\PhoneNumber;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Matching\WorkshopMerger;
use App\Workshops\Sources\Onrc\OnrcCompanyParser;
use App\Workshops\Sources\Osm\OsmPoiParser;
use App\Workshops\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What the matchers would not decide alone: pairs of workshops that may be one place, and source
 * records (an OSM point, an ONRC company) that fit more than one candidate or fit only loosely.
 * Every decision here is recorded with who took it and is never overturned by the next import.
 */
#[Layout('layouts::admin')]
class WorkshopReviewQueue extends Component
{
    use WithPagination;

    #[Url(except: 'duplicates')]
    public string $tab = 'duplicates';

    public function updated(string $property): void
    {
        if ($property === 'tab') {
            $this->resetPage();
        }
    }

    public function merge(int $candidateId): void
    {
        $pair = WorkshopMatchCandidate::query()->with(['workshopA', 'workshopB'])->findOrFail($candidateId);

        if ($pair->workshopA === null || $pair->workshopB === null || $pair->workshopA->merged_into_id !== null || $pair->workshopB->merged_into_id !== null) {
            $pair->update(['status' => WorkshopMatchStatus::Rejected, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

            return;
        }

        [$survivor, $duplicate] = WorkshopMerger::order($pair->workshopA, $pair->workshopB);
        app(WorkshopMerger::class)->merge($survivor, $duplicate, (array) $pair->evidence, Auth::id(), (int) $pair->score);
        session()->flash('status', "„{$duplicate->name}” a fost unit în „{$survivor->name}”.");
    }

    public function keepApart(int $candidateId): void
    {
        WorkshopMatchCandidate::query()->whereKey($candidateId)->update(['status' => WorkshopMatchStatus::Rejected, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
    }

    public function linkRecord(int $matchId, int $targetId): void
    {
        $match = WorkshopRecordMatch::query()->with('sourceRecord.dataSource')->findOrFail($matchId);
        $match->update(['status' => WorkshopRecordMatchStatus::Matched, 'target_id' => $targetId, 'method' => 'reviewed', 'reviewed_by' => Auth::id(), 'decided_at' => now()]);
        app(RecordNormalizers::class)->normalizeSafely($match->sourceRecord);
    }

    public function rejectRecord(int $matchId): void
    {
        $match = WorkshopRecordMatch::query()->with('sourceRecord.dataSource')->findOrFail($matchId);
        $match->update(['status' => WorkshopRecordMatchStatus::Rejected, 'target_id' => null, 'reviewed_by' => Auth::id(), 'decided_at' => now()]);
        app(RecordNormalizers::class)->normalizeSafely($match->sourceRecord);
    }

    public function render()
    {
        $pairs = WorkshopMatchCandidate::query()
            ->with(['workshopA.company:id,cui', 'workshopB.company:id,cui'])
            ->where('status', WorkshopMatchStatus::Pending)
            ->orderByDesc('score')
            ->paginate(25, pageName: 'pairs');

        $records = WorkshopRecordMatch::query()
            ->with('sourceRecord.dataSource:id,key,name')
            ->whereNull('reviewed_by')
            ->whereIn('status', [WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])
            ->orderByDesc('score')
            ->paginate(25, pageName: 'records');

        $matches = collect($records->items());
        $ids = fn (string $type, string $key): array => $matches->where('target_type', $type)
            ->flatMap(fn (WorkshopRecordMatch $match) => collect((array) $match->candidates)->pluck($key)->push($match->target_id))
            ->filter()->unique()->values()->all();

        $workshops = Workshop::query()
            ->with(['company:id,cui', 'contacts' => fn ($query) => $query->whereIn('type', ['phone', 'mobile'])])
            ->whereIn('id', $ids(WorkshopRecordMatch::TARGET_WORKSHOP, 'workshop_id'))
            ->get()
            ->keyBy('id');
        $companies = WorkshopCompany::query()->whereIn('id', $ids(WorkshopRecordMatch::TARGET_COMPANY, 'company_id'))->get()->keyBy('id');

        return view('livewire.admin.workshops.review', [
            'pairs' => $pairs,
            'records' => $records,
            'reviewItems' => $matches->map(fn (WorkshopRecordMatch $match): array => $this->reviewItem($match, $workshops, $companies))->all(),
            'counts' => [
                'duplicates' => WorkshopMatchCandidate::query()->where('status', WorkshopMatchStatus::Pending)->count(),
                'records' => WorkshopRecordMatch::query()->whereNull('reviewed_by')->whereIn('status', [WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])->count(),
            ],
        ]);
    }

    /**
     * One source record and its candidates in the terms a person compares: what the OSM point or
     * the ONRC company says about itself, and for each candidate its address, fiscal code, phones
     * and how far it is from the point. A chain's branches share a name, so the name alone decides
     * nothing.
     *
     * @param  Collection<int, Workshop>  $workshops
     * @param  Collection<int, WorkshopCompany>  $companies
     * @return array{match: WorkshopRecordMatch, subject: array<string, mixed>, candidates: list<array<string, mixed>>}
     */
    private function reviewItem(WorkshopRecordMatch $match, Collection $workshops, Collection $companies): array
    {
        $subject = $this->subject($match);
        $candidates = [];

        foreach (array_slice((array) $match->candidates, 0, 5) as $candidate) {
            if ($match->target_type === WorkshopRecordMatch::TARGET_COMPANY) {
                $id = $candidate['company_id'] ?? null;
                $company = $companies->get($id);
                $row = [
                    'name' => $company?->legal_name ?? $candidate['legal_name'] ?? null,
                    'address' => $company?->registered_address,
                    'place' => $company?->registered_locality,
                    'cui' => $company?->cui,
                    'phones' => [],
                    'distance' => null,
                    'approximate' => false,
                    'url' => null,
                    'map' => null,
                ];
            } else {
                $id = $candidate['workshop_id'] ?? null;
                $workshop = $workshops->get($id);
                $row = [
                    'name' => $workshop?->name ?? $candidate['name'] ?? null,
                    'address' => $workshop?->address,
                    'place' => collect([$workshop?->locality, $workshop?->county_code])->filter()->implode(', '),
                    'cui' => $workshop?->company?->cui,
                    'phones' => $workshop?->contacts->pluck('normalized_value')->unique()->values()->all() ?? [],
                    'distance' => $workshop?->hasCoordinates() && $subject['latitude'] !== null
                        ? (int) round(Geo::distanceMeters($subject['latitude'], $subject['longitude'], $workshop->latitude, $workshop->longitude))
                        : null,
                    // A town-level point is only where the town is: its distance proves little.
                    'approximate' => $workshop !== null && ($workshop->coordinates_confidence ?? 0) < 45,
                    'url' => $workshop !== null ? route('admin.workshops.show', $workshop) : null,
                    'map' => $workshop?->hasCoordinates() ? $this->mapUrl($workshop->latitude, $workshop->longitude) : null,
                ];
            }

            if ($id === null) {
                continue;
            }

            $candidates[] = $row + [
                'id' => (int) $id,
                'signals' => $this->signals($candidate),
                'suggested' => (int) $match->target_id === (int) $id,
            ];
        }

        return ['match' => $match, 'subject' => $subject, 'candidates' => $candidates];
    }

    /** @return array{name: ?string, address: ?string, locality: ?string, phones: list<string>, websites: list<string>, details: list<string>, latitude: ?float, longitude: ?float, url: ?string, map: ?string} */
    private function subject(WorkshopRecordMatch $match): array
    {
        $payload = (array) $match->sourceRecord?->payload;

        if ($match->target_type === WorkshopRecordMatch::TARGET_COMPANY) {
            $company = app(OnrcCompanyParser::class)->parse($payload);

            return [
                'name' => $company->legalName,
                'address' => $company->office->address,
                'locality' => $company->office->locality,
                'phones' => [],
                'websites' => array_values(array_filter([$company->website])),
                'details' => array_values(array_filter([$company->cui !== null ? 'CUI '.$company->cui : 'fără CUI', $company->registrationNumber, $company->statusLabel()])),
                'latitude' => null,
                'longitude' => null,
                'url' => null,
                'map' => null,
            ];
        }

        $poi = app(OsmPoiParser::class)->parse($payload);

        return [
            'name' => $poi->name ?? $poi->operator ?? $poi->brand,
            'address' => $poi->location->address,
            'locality' => $poi->location->locality,
            'phones' => array_map(fn (PhoneNumber $phone): string => $phone->display(), $poi->phones),
            'websites' => $poi->websites,
            'details' => array_values(array_filter([
                $poi->operator !== null && $poi->operator !== $poi->name ? 'operator: '.$poi->operator : null,
                $poi->brand !== null && $poi->brand !== $poi->name ? 'marcă: '.$poi->brand : null,
            ])),
            'latitude' => $poi->latitude,
            'longitude' => $poi->longitude,
            'url' => $poi->url(),
            'map' => $poi->hasCoordinates() ? $this->mapUrl($poi->latitude, $poi->longitude) : null,
        ];
    }

    /** @return list<string> why the matcher proposed a candidate, in words */
    private function signals(array $candidate): array
    {
        $signals = (array) ($candidate['signals'] ?? $candidate);
        $percent = fn (mixed $value): string => (int) round((float) $value * 100).'%';

        return array_values(array_filter([
            ! empty($signals['phone']) ? 'același telefon' : null,
            ! empty($signals['website']) ? 'același site' : null,
            isset($signals['name_similarity']) ? 'nume '.$percent($signals['name_similarity']) : null,
            isset($signals['address_similarity']) ? 'adresă '.$percent($signals['address_similarity']) : null,
            ! empty($signals['same_locality']) ? 'aceeași localitate' : null,
        ]));
    }

    private function mapUrl(float $latitude, float $longitude): string
    {
        return sprintf('https://www.openstreetmap.org/?mlat=%1$.6F&mlon=%2$.6F#map=18/%1$.6F/%2$.6F', $latitude, $longitude);
    }
}
