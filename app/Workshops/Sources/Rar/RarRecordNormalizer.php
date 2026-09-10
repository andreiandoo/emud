<?php

namespace App\Workshops\Sources\Rar;

use App\Models\Workshop;
use App\Models\WorkshopAuthorization;
use App\Models\WorkshopAuthorizationActivity;
use App\Models\WorkshopCompany;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Contracts\SourceRecordNormalizer;
use App\Workshops\Domain\CompanyResolver;
use App\Workshops\Domain\ContactWriter;
use App\Workshops\Domain\WorkshopStateRefresher;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Support\AddressNormalizer;
use App\Workshops\Support\CompanyNameNormalizer;
use App\Workshops\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns one stored RAR authorisation into a company, a workshop, its contacts, the authorisation
 * with every activity code, and everything derived from them.
 *
 * Which workshop a record belongs to is decided in this order, most certain first:
 *  1. the workshop this record is already linked to;
 *  2. the workshop an earlier revision of the same authorisation is linked to (same audit file
 *     or ITP station), since a renewal is the same place;
 *  3. a workshop of the same company at the same address from any RAR section, since an ITP
 *     station inside a service is one place with two authorisations;
 *  4. otherwise a new workshop.
 * A company's other workshops at other addresses are never merged: one company, many places.
 */
class RarRecordNormalizer implements SourceRecordNormalizer
{
    public function __construct(
        private RarAuthorizationParser $parser,
        private CompanyResolver $companies,
        private ContactWriter $contacts,
        private WorkshopStateRefresher $state,
    ) {}

    public function normalize(WorkshopSourceRecord $record): void
    {
        $record->loadMissing('dataSource');
        $section = DataSourceCatalog::rarSectionFor((string) $record->dataSource?->key);

        if ($section === null) {
            throw new RarParseException("Record {$record->id} does not belong to a RAR registry section.");
        }

        if ($record->record_type !== 'authorization') {
            $record->markSkipped('Not an authorisation record.');

            return;
        }

        $data = $this->parser->parse($section, (array) $record->payload);

        DB::transaction(function () use ($record, $data): void {
            $company = $this->companies->fromRar($data->company);
            $previousWorkshopId = WorkshopSourceLink::query()->where('source_record_id', $record->id)->value('workshop_id');
            [$workshop, $matchType, $confidence] = $this->resolveWorkshop($record, $data, $company);

            $this->fill($workshop, $data, $company, $record);
            $workshop->save();

            WorkshopSourceLink::query()->updateOrCreate(
                ['source_record_id' => $record->id],
                [
                    'workshop_id' => $workshop->id,
                    'data_source_id' => $record->data_source_id,
                    'external_id' => $record->external_id,
                    'match_type' => $matchType,
                    'match_confidence' => $confidence,
                    'evidence' => ['identity_key' => $record->identity_key, 'audit_file' => $data->auditFileNumber, 'station_code' => $data->stationCode],
                ],
            );

            $this->writeContacts($workshop, $data, $record);
            $this->writeAuthorization($workshop, $company, $record, $data);
            $this->state->refresh($workshop);

            if ($previousWorkshopId !== null && (int) $previousWorkshopId !== $workshop->id && ($previous = Workshop::query()->find($previousWorkshopId)) !== null) {
                $this->state->refresh($previous);
            }

            $record->markParsed();
        });
    }

    /** @return array{0: Workshop, 1: string, 2: int} */
    private function resolveWorkshop(WorkshopSourceRecord $record, RarAuthorizationData $data, WorkshopCompany $company): array
    {
        $link = WorkshopSourceLink::query()->with('workshop')->where('source_record_id', $record->id)->first();

        if ($link?->workshop !== null) {
            return [$this->canonical($link->workshop), $link->match_type, $link->match_confidence];
        }

        if ($record->identity_key !== null) {
            $revision = WorkshopSourceLink::query()
                ->with('workshop')
                ->whereIn('source_record_id', WorkshopSourceRecord::query()
                    ->select('id')
                    ->where('data_source_id', $record->data_source_id)
                    ->where('identity_key', $record->identity_key)
                    ->where('id', '!=', $record->id))
                ->latest('id')
                ->first();

            if ($revision?->workshop !== null) {
                return [$this->canonical($revision->workshop), 'rar_identity', 100];
            }
        }

        if ($data->location->address !== null) {
            $sameAddress = Workshop::query()
                ->canonical()
                ->where('company_id', $company->id)
                ->where('county_code', $data->location->countyCode)
                ->whereHas('authorizations')
                ->get()
                ->first(fn (Workshop $candidate): bool => AddressNormalizer::similarity($candidate->address, $data->location->address, $data->location->countyCode) >= 0.8);

            if ($sameAddress !== null) {
                return [$sameAddress, 'rar_company_address', 90];
            }
        }

        return [new Workshop, 'rar_external_id', 100];
    }

    private function fill(Workshop $workshop, RarAuthorizationData $data, WorkshopCompany $company, WorkshopSourceRecord $record): void
    {
        $location = $data->location;
        $name = $company->legal_name ?: $data->company->legalName;

        $workshop->company_id = $company->id;
        $workshop->name = $name;
        $workshop->normalized_name = CompanyNameNormalizer::normalize($name);

        if ($location->address !== null) {
            $workshop->address = $location->address;
            $workshop->normalized_address = AddressNormalizer::normalize($location->address);
            $workshop->street = $location->street;
            $workshop->street_number = $location->streetNumber;
            $workshop->locality = $location->locality;
            $workshop->normalized_locality = TextNormalizer::fold($location->locality) ?: null;
            $workshop->county = $location->countyName();
            $workshop->county_code = $location->countyCode;
            $workshop->postal_code = $location->postalCode ?? $workshop->postal_code;
        }

        // A point only replaces one we trust less. An OSM match placed on the gate is never moved
        // back to a town-level RAR point by the next weekly import.
        if ($location->hasCoordinates() && $location->coordinateConfidence > (int) $workshop->coordinates_confidence) {
            $workshop->latitude = $location->latitude;
            $workshop->longitude = $location->longitude;
            $workshop->coordinates_source = 'rar';
            $workshop->coordinates_confidence = $location->coordinateConfidence;
            $workshop->geocode_status = $location->coordinateQuality === 'precise' ? 'located' : 'approximate';
        } elseif (! $workshop->hasCoordinates()) {
            $workshop->geocode_status = 'pending';
        }

        $workshop->workstations = max((int) $workshop->workstations, (int) $data->workstations) ?: null;
        $workshop->employees = max((int) $workshop->employees, (int) $data->employees) ?: null;
        $workshop->is_mobile = (bool) $workshop->is_mobile || $data->isMobile;
        $workshop->slug ??= Str::limit(Str::slug($name.' '.$location->locality), 180, '');
        $workshop->first_seen_at = $workshop->first_seen_at === null || $record->first_seen_at?->lessThan($workshop->first_seen_at)
            ? $record->first_seen_at
            : $workshop->first_seen_at;
        $workshop->last_seen_at = $record->last_seen_at;
        $workshop->last_verified_at = $record->fetched_at;
    }

    private function writeContacts(Workshop $workshop, RarAuthorizationData $data, WorkshopSourceRecord $record): void
    {
        foreach ($data->phones as $phone) {
            $this->contacts->phone($workshop, $phone, $record, 85, 'Punct de lucru (RAR)', $record->source_reference);
        }

        foreach ($data->company->phones as $phone) {
            $this->contacts->phone($workshop, $phone, $record, 70, 'Sediu social (RAR)', $record->source_reference);
        }

        foreach ($data->company->emails as $email) {
            $this->contacts->email($workshop, $email, $record, 70, 'Email firmă (RAR)', $record->source_reference);
        }
    }

    private function writeAuthorization(Workshop $workshop, WorkshopCompany $company, WorkshopSourceRecord $record, RarAuthorizationData $data): void
    {
        $authorization = WorkshopAuthorization::query()->updateOrCreate(
            ['source_record_id' => $record->id],
            [
                'workshop_id' => $workshop->id,
                'company_id' => $company->id,
                'data_source_id' => $record->data_source_id,
                'authority' => 'RAR',
                'system' => $data->system,
                'status' => $data->status,
                'authorization_number' => $data->number,
                'exit_number' => $data->exitNumber,
                'audit_file_number' => $data->auditFileNumber,
                'station_code' => $data->stationCode,
                'authorization_class' => $data->authorizationClass,
                'valid_from' => $data->validFrom?->toDateString(),
                'valid_until' => $data->validUntil?->toDateString(),
                'initially_authorized_at' => $data->initiallyAuthorizedAt?->toDateString(),
                'revision_number' => $data->revisionNumber,
                'revision_date' => $data->revisionDate?->toDateString(),
                'is_current' => (bool) $record->is_current,
                'raw_data' => $data->summary + ['brands' => $data->brands],
            ],
        );

        $authorization->activities()->delete();
        $now = now();
        $rows = [];

        foreach ($data->activities as $activity) {
            $rows[] = [
                'workshop_authorization_id' => $authorization->id,
                'code' => $activity->code,
                'display_code' => $activity->displayCode(),
                'parent_code' => $activity->parentCode,
                'entry_code' => $activity->entryCode,
                'depth' => $activity->depth,
                'description' => $activity->description(),
                'raw_description' => $activity->rawDescription,
                'vehicle_categories' => $this->json($activity->vehicleCategories),
                'restrictions' => $this->json($activity->restrictions),
                'limitations' => $this->json($activity->limitations),
                'observations' => $this->json($activity->observations),
                'raw_data' => $this->json($activity->raw),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            WorkshopAuthorizationActivity::query()->insert($chunk);
        }
    }

    private function canonical(Workshop $workshop): Workshop
    {
        for ($hops = 0; $workshop->merged_into_id !== null && $hops < 10; $hops++) {
            $target = $workshop->mergedInto;

            if ($target === null) {
                break;
            }

            $workshop = $target;
        }

        return $workshop;
    }

    private function json(?array $value): ?string
    {
        return $value === null || $value === [] ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
