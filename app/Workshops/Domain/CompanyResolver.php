<?php

namespace App\Workshops\Domain;

use App\Models\WorkshopCompany;
use App\Workshops\Data\CompanyData;
use App\Workshops\Support\CompanyNameNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Finds or creates the legal entity behind a record.
 *
 * The fiscal code decides when there is one. Without it a company is matched only on its exact
 * normalised name in the same county, and a name that fits several is never guessed at.
 *
 * ONRC is the authority on legal identity. Once a company has been confirmed against it, RAR's
 * copy of the name and registered office no longer overwrites it; before that, RAR's copy is the
 * best there is and is kept current.
 */
class CompanyResolver
{
    public function fromRar(CompanyData $data): WorkshopCompany
    {
        $company = $this->find($data) ?? new WorkshopCompany(['cui' => $data->cui, 'discovered_via' => 'rar']);
        $office = $data->registeredOffice;

        if ($company->onrc_verified_at === null) {
            $company->fill([
                'legal_name' => $data->legalName,
                'normalized_name' => CompanyNameNormalizer::normalize($data->legalName),
                'legal_form' => CompanyNameNormalizer::legalForm($data->legalName),
                'registration_number' => $data->registrationNumber ?? $company->registration_number,
                'registered_address' => $office?->address ?? $company->registered_address,
                'registered_locality' => $office?->locality ?? $company->registered_locality,
                'registered_county' => $office?->countyName() ?? $company->registered_county,
                'county_code' => $office?->countyCode ?? $company->county_code,
                'registered_postal_code' => $office?->postalCode ?? $company->registered_postal_code,
                'source_confidence' => 80,
            ]);
        }

        if ($office !== null && $office->coordinateQuality === 'precise') {
            $company->registered_latitude = $office->latitude;
            $company->registered_longitude = $office->longitude;
        }

        // RAR's fiscal indicator is refreshed with every authorisation, so it is current.
        $company->is_vat_payer = $data->isVatPayer ?? $company->is_vat_payer;

        try {
            // Inside its own savepoint: on PostgreSQL a failed statement poisons the whole
            // transaction it runs in, and the caller's transaction must survive this one.
            DB::transaction(fn () => $company->save());
        } catch (UniqueConstraintViolationException) {
            // The same fiscal code was created by a parallel job an instant ago.
            $company = WorkshopCompany::query()->where('cui', $data->cui)->firstOrFail();
        }

        return $company;
    }

    private function find(CompanyData $data): ?WorkshopCompany
    {
        if ($data->cui !== null) {
            return WorkshopCompany::query()->where('cui', $data->cui)->first();
        }

        $matches = WorkshopCompany::query()
            ->whereNull('cui')
            ->where('normalized_name', CompanyNameNormalizer::normalize($data->legalName))
            ->when($data->registeredOffice?->countyCode, fn ($query, string $county) => $query->where('county_code', $county))
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
