<?php

namespace App\Workshops\Matching;

use App\Models\Workshop;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\Geo;

/**
 * How likely two workshops are to be the same place, and whether that is certain enough to merge
 * without a person looking.
 *
 * Same company is not same workshop. A chain answers every branch on one phone line and gives them
 * all the same name, so for two workshops of one company, or two that each hold their own RAR
 * authorisation, only the same address can make them one place. Nearness alone never merges.
 *
 * Nor are two companies made one workshop without a person. An owner's service firm and ITP firm
 * at one gate, and two tenants of one yard sharing the landlord's number, look alike in the data;
 * such a pair waits in the review queue with its score.
 */
class WorkshopPairScorer
{
    /** @return array{score: int, auto: bool, evidence: array<string, mixed>} */
    public function score(Workshop $a, Workshop $b): array
    {
        $score = 0;
        $evidence = [];

        $phones = array_intersect($this->contacts($a, ['phone', 'mobile']), $this->contacts($b, ['phone', 'mobile']));
        if ($phones !== []) {
            $score += 40;
            $evidence['shared_phones'] = array_values($phones);
        }

        $websites = array_intersect($this->hosts($a), $this->hosts($b));
        if ($websites !== []) {
            $score += 40;
            $evidence['shared_websites'] = array_values($websites);
        }

        $name = CompanyNameNormalizer::similarity($a->name, $b->name);
        $evidence['name_similarity'] = $name;
        $score += match (true) {
            $name >= 0.9 => 30,
            $name >= 0.7 => 20,
            $name >= 0.5 => 10,
            default => 0,
        };

        $address = AddressNormalizer::similarity($a->address, $b->address, $a->county_code);
        $evidence['address_similarity'] = $address;
        $score += match (true) {
            $address >= 0.9 => 35,
            $address >= 0.7 => 20,
            default => 0,
        };

        $sameLocality = $a->normalized_locality !== null && $a->normalized_locality === $b->normalized_locality;
        $score += $sameLocality ? 5 : 0;

        $distance = null;
        if ($a->hasCoordinates() && $b->hasCoordinates() && ($a->coordinates_confidence ?? 0) >= 45 && ($b->coordinates_confidence ?? 0) >= 45) {
            $distance = Geo::distanceMeters($a->latitude, $a->longitude, $b->latitude, $b->longitude);
            $evidence['distance_m'] = (int) round($distance);
            $score += match (true) {
                $distance <= 30 => 20,
                $distance <= 100 => 10,
                $distance > 1000 => -30,
                default => 0,
            };
        }

        $sameCompany = $a->company_id !== null && $a->company_id === $b->company_id;
        $score += $sameCompany ? 10 : 0;
        $evidence['same_company'] = $sameCompany;

        $differentCompanies = $a->company_id !== null && $b->company_id !== null && ! $sameCompany;
        if ($differentCompanies) {
            $evidence['different_companies'] = true;
        }

        $bothAuthorised = $this->hasCurrentAuthorization($a) && $this->hasCurrentAuthorization($b);
        $evidence['both_rar_authorised'] = $bothAuthorised;

        $identity = $phones !== [] || $websites !== [] || $name >= 0.7 || $address >= 0.9;
        $samePlace = $sameLocality || ($distance !== null && $distance <= 300) || $address >= 0.9;
        $distinctByDefinition = ($bothAuthorised || $sameCompany) && $address < 0.9;

        // A company's branches in different streets are different places; they do not belong in
        // the review queue either, unless the points put them in the same yard.
        if ($distinctByDefinition && $address < 0.7 && ($distance === null || $distance > 100)) {
            $score = min($score, 40);
            $evidence['kept_apart'] = 'same company or both RAR-authorised, at different addresses';
        }

        return [
            'score' => max(0, min(100, $score)),
            'auto' => $identity && $samePlace && ! $distinctByDefinition && ! $differentCompanies,
            'evidence' => $evidence,
        ];
    }

    /** @return list<string> */
    private function contacts(Workshop $workshop, array $types): array
    {
        return $workshop->contacts->whereIn('type', $types)->pluck('normalized_value')->unique()->values()->all();
    }

    /** @return list<string> */
    private function hosts(Workshop $workshop): array
    {
        return $workshop->contacts->where('type', 'website')->map(fn ($contact): string => explode('/', $contact->normalized_value)[0])->unique()->values()->all();
    }

    private function hasCurrentAuthorization(Workshop $workshop): bool
    {
        return $workshop->authorizations->contains(fn ($authorization): bool => (bool) $authorization->is_current);
    }
}
