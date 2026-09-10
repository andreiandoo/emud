<?php

namespace App\Workshops\Sources\Onrc;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopCompany;
use App\Workshops\Matching\MatchDecision;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\TextNormalizer;

/**
 * Matches an ONRC company to a company the registry already holds, deterministically first:
 *
 *  1. fiscal code, exact: the same company;
 *  2. normalised legal name in the same locality or county: probable when exactly one fits;
 *  3. legal name and registered-office address: probable when the addresses agree.
 *
 * (Name plus phone, the fourth level in the plan, has nothing to work with: the open register
 * publishes no phone numbers.) Only level 1 updates a company on its own. A probable match is
 * recorded for review, and a name that fits several companies is ambiguous, never guessed.
 */
class OnrcCompanyMatcher
{
    public function match(OnrcCompany $company): MatchDecision
    {
        if ($company->cui !== null) {
            $byCui = WorkshopCompany::query()->where('cui', $company->cui)->first();

            if ($byCui !== null) {
                return new MatchDecision(WorkshopRecordMatchStatus::Matched, $byCui->id, 'cui', 100, evidence: ['cui' => $company->cui]);
            }

            // A fiscal code the registry does not hold is a different company, even with an
            // identical name: two firms may share a name in different counties.
            return new MatchDecision(WorkshopRecordMatchStatus::Unmatched, method: 'cui', evidence: ['cui' => $company->cui]);
        }

        $name = CompanyNameNormalizer::normalize($company->legalName);
        $candidates = WorkshopCompany::query()
            ->whereNull('cui')
            ->where('normalized_name', $name)
            ->when($company->office->countyCode, fn ($query, string $county) => $query->where('county_code', $county))
            ->limit(20)
            ->get();

        if ($candidates->isEmpty()) {
            return new MatchDecision(WorkshopRecordMatchStatus::Unmatched, method: 'name_locality');
        }

        $locality = TextNormalizer::fold($company->office->locality);
        $scored = $candidates->map(function (WorkshopCompany $candidate) use ($company, $locality): array {
            $sameLocality = $locality !== '' && TextNormalizer::fold($candidate->registered_locality) === $locality;
            $address = AddressNormalizer::similarity($candidate->registered_address, $company->office->address, $company->office->countyCode);

            return [
                'company_id' => $candidate->id,
                'legal_name' => $candidate->legal_name,
                'same_locality' => $sameLocality,
                'address_similarity' => $address,
                'score' => 60 + ($sameLocality ? 20 : 0) + (int) round($address * 20),
            ];
        })->sortByDesc('score')->values();

        $best = $scored->first();
        $runnerUp = $scored->get(1);

        if ($runnerUp !== null && $runnerUp['score'] >= $best['score'] - 10) {
            return new MatchDecision(WorkshopRecordMatchStatus::Ambiguous, method: 'name_locality', score: $best['score'], candidates: $scored->all());
        }

        return new MatchDecision(
            WorkshopRecordMatchStatus::Probable,
            $best['company_id'],
            $best['address_similarity'] >= 0.8 ? 'name_address' : 'name_locality',
            $best['score'],
            $scored->all(),
        );
    }
}
