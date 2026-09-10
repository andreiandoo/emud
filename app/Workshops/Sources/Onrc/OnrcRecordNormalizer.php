<?php

namespace App\Workshops\Sources\Onrc;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopCompany;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Contracts\SourceRecordNormalizer;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Matching\MatchDecision;
use App\Workshops\Matching\RecordMatchRecorder;
use App\Workshops\Support\CompanyNameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Applies one ONRC company record: confirms and completes the legal identity of a company known
 * from RAR, or records an operating repair company RAR does not list as a lead.
 *
 * ONRC is never taken as proof of a workshop. A registered office is where the paperwork lives;
 * an ONRC-only company gets no workshop until OpenStreetMap or its own website shows a real place.
 */
class OnrcRecordNormalizer implements SourceRecordNormalizer
{
    public function __construct(
        private OnrcCompanyParser $parser,
        private OnrcCompanyMatcher $matcher,
        private RecordMatchRecorder $matches,
        private WorkshopStateRefresher $state,
    ) {}

    public function normalize(WorkshopSourceRecord $record): void
    {
        $company = $this->parser->parse((array) $record->payload);

        DB::transaction(function () use ($record, $company): void {
            $reviewed = $this->matches->reviewed($record, WorkshopRecordMatch::TARGET_COMPANY);
            $decision = $reviewed !== null
                ? new MatchDecision($reviewed->status, $reviewed->target_id, $reviewed->method, $reviewed->score)
                : $this->matcher->match($company);

            if ($reviewed === null) {
                $this->matches->record($record, WorkshopRecordMatch::TARGET_COMPANY, $decision);
            }

            $target = match (true) {
                $decision->isLinked() => WorkshopCompany::query()->find($decision->targetId),
                $decision->status === WorkshopRecordMatchStatus::Unmatched && $company->automotive && $company->isActive() => new WorkshopCompany(['discovered_via' => 'onrc']),
                default => null,
            };

            if ($target !== null) {
                $this->apply($target, $company, $record);

                if ($decision->status === WorkshopRecordMatchStatus::Unmatched) {
                    $this->matches->record($record, WorkshopRecordMatch::TARGET_COMPANY, new MatchDecision(WorkshopRecordMatchStatus::Matched, $target->id, 'onrc_created', 100));
                }

                foreach ($target->workshops()->canonical()->get() as $workshop) {
                    $this->state->refresh($workshop);
                }
            }

            $record->markParsed();
        });
    }

    private function apply(WorkshopCompany $target, OnrcCompany $company, WorkshopSourceRecord $record): void
    {
        $target->fill([
            'cui' => $company->cui ?? $target->cui,
            'legal_name' => $company->legalName,
            'normalized_name' => CompanyNameNormalizer::normalize($company->legalName),
            'legal_form' => $company->legalForm ?? CompanyNameNormalizer::legalForm($company->legalName),
            'registration_number' => $company->registrationNumber,
            'euid' => $company->euid,
            'status' => $company->statusLabel(),
            'status_code' => $company->statusCode(),
            'registered_address' => $company->office->address,
            'registered_locality' => $company->office->locality,
            'registered_county' => $company->office->countyName(),
            'county_code' => $company->office->countyCode,
            'registered_postal_code' => $company->office->postalCode,
            'caen_codes' => $company->caen,
            'has_automotive_caen' => $company->automotive,
            'website' => $company->website ?? $target->website,
            'source_confidence' => 95,
            'onrc_verified_at' => $record->fetched_at ?? now(),
        ]);

        $target->save();
    }
}
