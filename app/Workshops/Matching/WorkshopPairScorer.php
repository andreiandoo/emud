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
 * The fiscal code decides first. Two CUIs are two workshops, whatever they share: an owner's service
 * firm and ITP firm at one gate, or two tenants of one yard, are kept apart and never queued. One
 * CUI at one place is one workshop, merged however the address was typed; one CUI at two house
 * numbers, or on two streets, is two branches. Without a CUI on both sides the signals below decide, and nearness
 * alone never merges.
 */
class WorkshopPairScorer
{
    /** @return array{score: int, auto: bool, certain: bool, evidence: array<string, mixed>} */
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

        $bothAuthorised = $this->hasCurrentAuthorization($a) && $this->hasCurrentAuthorization($b);
        $evidence['both_rar_authorised'] = $bothAuthorised;

        // The very same point at two plainly different addresses is one point, usually the office's,
        // copied to every branch: it puts nobody in the same yard.
        $copiedPoint = $distance !== null && $distance < 1 && $address < 0.5;
        $nearby = $distance !== null && $distance <= 100 && ! $copiedPoint;

        $cuiA = $a->company?->cui;
        $cuiB = $b->company?->cui;

        if ($cuiA !== null && $cuiB !== null && $cuiA !== $cuiB) {
            return $this->keptApart($score, $evidence, 'different companies (CUI)');
        }

        if ($sameCompany) {
            if (AddressNormalizer::houseNumbersDisagree($a->address, $b->address, $a->county_code)) {
                return $this->keptApart($score, $evidence, 'one company, different house numbers');
            }

            $sameStreet = AddressNormalizer::sameStreet($a->address, $b->address, $a->county_code, [$a->locality, $b->locality]);
            $evidence['same_street'] = $sameStreet;

            if ($sameStreet === false && ! $nearby) {
                return $this->keptApart($score, $evidence, 'one company, different streets');
            }

            // Real points far apart are overruled only by a house number both addresses give: one
            // side may name just the street, or just the town.
            $farApart = $distance !== null && $distance > 300
                && ! AddressNormalizer::houseNumbersAgree($a->address, $b->address, $a->county_code);

            if ($sameStreet !== false && ($nearby || $sameLocality) && ! $farApart) {
                return ['score' => max(0, min(100, $score)), 'auto' => true, 'certain' => true, 'evidence' => $evidence];
            }
        }

        $identity = $phones !== [] || $websites !== [] || $name >= 0.7 || $address >= 0.9;
        $samePlace = $sameLocality || ($distance !== null && $distance <= 300) || $address >= 0.9;
        $distinctByDefinition = ($bothAuthorised || $sameCompany) && $address < 0.9;

        // A company's branches in different streets are different places; they do not belong in
        // the review queue either, unless the points put them in the same yard.
        if ($distinctByDefinition && $address < 0.7 && ($distance === null || $distance > 100 || $copiedPoint)) {
            return $this->keptApart($score, $evidence, 'same company or both RAR-authorised, at different addresses');
        }

        return [
            'score' => max(0, min(100, $score)),
            'auto' => $identity && $samePlace && ! $distinctByDefinition,
            'certain' => false,
            'evidence' => $evidence,
        ];
    }

    /** @return array{score: int, auto: bool, certain: bool, evidence: array<string, mixed>} */
    private function keptApart(int $score, array $evidence, string $why): array
    {
        $evidence['kept_apart'] = $why;

        return ['score' => max(0, min(40, $score)), 'auto' => false, 'certain' => false, 'evidence' => $evidence];
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
