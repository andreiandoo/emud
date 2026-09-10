<?php

namespace App\Workshops\Sources\Osm;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\Workshop;
use App\Models\WorkshopContact;
use App\Workshops\Domain\ContactWriter;
use App\Workshops\Matching\MatchDecision;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\Geo;
use App\Workshops\Support\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * Decides whether an OpenStreetMap workshop is one the registry already holds.
 *
 * Candidates come from four directions (same phone, same website, nearby, same locality) and
 * each is scored on identity and place together. Being close is never enough on its own: two
 * workshops share a street all the time. A match needs an identity signal (phone, website, a
 * similar name or the same address), a clear winner, and a score of at least 70; a weaker or
 * contested one is kept for review instead of being linked or turned into a new workshop.
 */
class OsmWorkshopMatcher
{
    public const MATCH_SCORE = 70;

    public const REVIEW_SCORE = 45;

    public function match(OsmPoi $poi): MatchDecision
    {
        $candidates = $this->candidates($poi);

        if ($candidates->isEmpty()) {
            return new MatchDecision(WorkshopRecordMatchStatus::Unmatched, method: 'none');
        }

        $scored = $candidates->map(fn (Workshop $workshop): array => $this->score($poi, $workshop))
            ->sortByDesc('score')
            ->values();

        $best = $scored->first();
        $runnerUp = $scored->get(1);

        if ($best['score'] >= self::MATCH_SCORE && $best['identity']) {
            if ($runnerUp !== null && $runnerUp['score'] >= $best['score'] - 15 && $runnerUp['identity']) {
                return new MatchDecision(WorkshopRecordMatchStatus::Ambiguous, method: $best['method'], score: $best['score'], candidates: $scored->take(5)->all());
            }

            return new MatchDecision(WorkshopRecordMatchStatus::Matched, $best['workshop_id'], $best['method'], min(100, $best['score']), $scored->take(5)->all(), $best['signals']);
        }

        if ($best['score'] >= self::REVIEW_SCORE && $best['identity']) {
            return new MatchDecision(WorkshopRecordMatchStatus::Probable, $best['workshop_id'], $best['method'], $best['score'], $scored->take(5)->all(), $best['signals']);
        }

        return new MatchDecision(WorkshopRecordMatchStatus::Unmatched, method: 'below_threshold', score: $best['score'], candidates: $scored->take(3)->all());
    }

    /** @return Collection<int, Workshop> */
    private function candidates(OsmPoi $poi): Collection
    {
        $ids = collect();

        if ($poi->phones !== []) {
            $ids = $ids->merge(WorkshopContact::query()->whereIn('type', ['phone', 'mobile'])->whereIn('normalized_value', array_map(fn ($phone) => $phone->e164, $poi->phones))->pluck('workshop_id'));
        }

        foreach ($poi->websites as $url) {
            $host = explode('/', ContactWriter::normalizeUrl($url))[0];
            $ids = $ids->merge(WorkshopContact::query()->where('type', 'website')->where(fn ($query) => $query->where('normalized_value', $host)->orWhere('normalized_value', 'like', $host.'/%'))->pluck('workshop_id'));
        }

        if ($poi->hasCoordinates()) {
            $box = Geo::boundingBox($poi->latitude, $poi->longitude, 1500);
            $ids = $ids->merge(Workshop::query()->canonical()
                ->whereBetween('latitude', [$box['min_lat'], $box['max_lat']])
                ->whereBetween('longitude', [$box['min_lng'], $box['max_lng']])
                ->limit(300)
                ->pluck('id'));
        }

        if (($locality = TextNormalizer::fold($poi->location->locality)) !== '' && $poi->name !== null) {
            $ids = $ids->merge(Workshop::query()->canonical()->where('normalized_locality', $locality)->limit(2000)->pluck('id'));
        }

        $ids = $ids->unique()->values();

        return $ids->isEmpty()
            ? collect()
            : Workshop::query()->canonical()->with(['contacts:id,workshop_id,type,normalized_value', 'company:id,legal_name'])->whereIn('id', $ids->all())->get();
    }

    /** @return array{workshop_id: int, name: string, score: int, identity: bool, method: string, signals: array<string, mixed>} */
    private function score(OsmPoi $poi, Workshop $workshop): array
    {
        $score = 0;
        $signals = [];
        $method = 'name_proximity';
        $identity = false;

        $phones = array_map(fn ($phone) => $phone->e164, $poi->phones);
        if ($phones !== [] && $workshop->contacts->whereIn('type', ['phone', 'mobile'])->whereIn('normalized_value', $phones)->isNotEmpty()) {
            $score += 60;
            $signals['phone'] = true;
            $identity = true;
            $method = 'phone';
        }

        $hosts = array_map(fn (string $url): string => explode('/', ContactWriter::normalizeUrl($url))[0], $poi->websites);
        if ($hosts !== [] && $workshop->contacts->where('type', 'website')->contains(fn (WorkshopContact $contact): bool => in_array(explode('/', $contact->normalized_value)[0], $hosts, true))) {
            $score += 55;
            $signals['website'] = true;
            $identity = true;
            $method = $method === 'phone' ? 'phone' : 'website';
        }

        $name = max(
            CompanyNameNormalizer::similarity($poi->name, $workshop->name),
            CompanyNameNormalizer::similarity($poi->operator, $workshop->name),
            CompanyNameNormalizer::similarity($poi->name, $workshop->company?->legal_name),
        );
        $signals['name_similarity'] = $name;

        if ($name >= 0.8) {
            $score += 35;
            $identity = true;
        } elseif ($name >= 0.5) {
            $score += 20;
            $identity = true;
        }

        $address = $poi->location->street !== null ? AddressNormalizer::similarity($poi->location->address, $workshop->address, $workshop->county_code) : 0.0;
        $signals['address_similarity'] = $address;

        if ($address >= 0.8) {
            $score += 30;
            $identity = true;
            $method = in_array($method, ['phone', 'website'], true) ? $method : 'name_address';
        } elseif ($address >= 0.5) {
            $score += 12;
        }

        if ($poi->hasCoordinates() && $workshop->hasCoordinates()) {
            $distance = Geo::distanceMeters($poi->latitude, $poi->longitude, $workshop->latitude, $workshop->longitude);
            $signals['distance_m'] = (int) round($distance);

            if (($workshop->coordinates_confidence ?? 0) >= 45) {
                $score += match (true) {
                    $distance <= 50 => 30,
                    $distance <= (int) config('workshops.osm.match_distance_meters', 200) => 20,
                    $distance <= 500 => 8,
                    $distance > 2000 => -20,
                    default => 0,
                };
            } elseif ($distance <= 3000) {
                // A town-level point says only that both are in the same town.
                $score += 5;
            }
        }

        if (($locality = TextNormalizer::fold($poi->location->locality)) !== '' && $locality === $workshop->normalized_locality) {
            $score += 8;
            $signals['same_locality'] = true;
        }

        return [
            'workshop_id' => $workshop->id,
            'name' => $workshop->name,
            'score' => max(0, $score),
            'identity' => $identity,
            'method' => $method,
            'signals' => $signals,
        ];
    }
}
