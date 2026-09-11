<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Models\WorkshopAuthorizationActivity;
use App\Models\WorkshopCompany;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopService;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Ingestion\RecordNormalizers;
use App\Workshops\Ingestion\SourceRecordStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class RarNormalizationTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    /** @return list<string> */
    private function services(Workshop $workshop, WorkshopEvidenceType $type): array
    {
        return WorkshopService::query()->with('serviceType')->where('workshop_id', $workshop->id)->where('evidence_type', $type->value)->get()->map(fn ($s) => $s->serviceType->key)->sort()->values()->all();
    }

    public function test_an_authorisation_becomes_a_company_a_workshop_and_its_activities(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));

        $company = $workshop->company;
        $this->assertSame('AUTO TEHNIC ȘERBAN S.R.L.', $company->legal_name);
        $this->assertSame('20963285', $company->cui);
        $this->assertSame('SRL', $company->legal_form);
        $this->assertTrue($company->is_vat_payer);
        $this->assertSame('rar', $company->discovered_via);

        $this->assertSame('AUTO TEHNIC ȘERBAN S.R.L.', $workshop->name);
        $this->assertSame('ȘOS. CRISTIANULUI NR. 6 (HALA REPARAȚII), BRAȘOV, JUDEȚUL BRAȘOV', $workshop->address);
        $this->assertSame('Brașov', $workshop->locality);
        $this->assertSame('BV', $workshop->county_code);
        $this->assertSame('Brașov', $workshop->county);
        $this->assertSame(45.6624831, $workshop->latitude);
        $this->assertSame('rar', $workshop->coordinates_source);
        $this->assertSame('located', $workshop->geocode_status);
        $this->assertSame(6, $workshop->workstations);
        $this->assertTrue($workshop->is_rar_authorized);
        $this->assertTrue($workshop->is_active);

        $authorization = $workshop->authorizations()->firstOrFail();
        $this->assertSame('SERVICE', $authorization->system);
        $this->assertSame('90001', $authorization->authorization_number);
        $this->assertSame('BV9001', $authorization->audit_file_number);
        $this->assertSame('CLASS_2,CLASS_3', $authorization->authorization_class);
        $this->assertSame('2026-12-31', $authorization->valid_until->toDateString());
        $this->assertSame('2009-06-15', $authorization->initially_authorized_at->toDateString());
        $this->assertSame(['DACIA', 'RENAULT'], $authorization->raw_data['brands']);

        $awd = WorkshopAuthorizationActivity::query()->where('workshop_authorization_id', $authorization->id)->where('code', 'A1_2_1_3')->firstOrFail();
        $this->assertSame('A1.2.1.3', $awd->display_code);
        $this->assertSame('A1_2_1', $awd->parent_code);
        $this->assertSame(3, $awd->depth);
        $this->assertStringContainsString('tracțiune permanentă pe mai multe axe', $awd->description);
        $this->assertEqualsCanonicalizing(['M1', 'N1', 'N2'], $awd->vehicle_categories);
        $this->assertSame(24, $authorization->activities()->count());
    }

    public function test_rar_codes_become_authorised_services_and_capabilities(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));

        $services = $this->services($workshop, WorkshopEvidenceType::RarAuthorization);
        foreach (['4x4_drivetrain', 'automatic_transmission', 'manual_transmission', 'electric_vehicle', 'wheel_alignment', 'axles', 'suspension', 'steering', 'truck_service', 'exhaust'] as $key) {
            $this->assertContains($key, $services);
        }
        $this->assertNotContains('hybrid', $services);
        $this->assertTrue(WorkshopService::query()->where('workshop_id', $workshop->id)->pluck('is_authorized')->every(fn ($value) => $value === true));

        $this->assertTrue($workshop->supports_4x4);
        $this->assertTrue($workshop->supports_ev);
        $this->assertNull($workshop->supports_hybrid);
        $this->assertTrue($workshop->supports_trucks);
        $this->assertSame(55, $workshop->offroad_score);
        $this->assertTrue($workshop->capabilities()->where('capability', 'awd_permanent')->firstOrFail()->value);
        $this->assertNull($workshop->capabilities()->where('capability', 'offroad')->firstOrFail()->value);
        $this->assertSame('rar_authorization', $workshop->capabilities()->where('capability', '4x4')->firstOrFail()->basis);
    }

    public function test_a_name_containing_4x4_proves_nothing(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_branch', ['branch' => ['organisationInfo' => ['name' => '4X4 OFFROAD EXPERT SRL', 'taxRegisterNo' => '77777770']]])));

        $this->assertNull($workshop->supports_4x4);
        $this->assertNull($workshop->offroad_score);
    }

    public function test_every_contact_keeps_its_source(): void
    {
        $record = $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $workshop = $this->workshopFor($record);

        $mobile = WorkshopContact::query()->where('workshop_id', $workshop->id)->where('normalized_value', '+40722000101')->firstOrFail();
        $this->assertSame('mobile', $mobile->type);
        $this->assertSame('Punct de lucru (RAR)', $mobile->label);
        $this->assertSame(85, $mobile->confidence_score);
        $this->assertSame($record->id, $mobile->source_record_id);
        $this->assertSame($record->data_source_id, $mobile->data_source_id);

        $this->assertSame('landline', PhoneFixture::type(WorkshopContact::query()->where('normalized_value', '+40268000102')->firstOrFail()->type));
        $this->assertSame(70, WorkshopContact::query()->where('normalized_value', '+40268000111')->firstOrFail()->confidence_score);
        $this->assertSame('office@autotehnicserban.ro', WorkshopContact::query()->where('type', 'email')->firstOrFail()->value);
        $this->assertSame(1, WorkshopContact::query()->where('workshop_id', $workshop->id)->whereIn('type', ['phone', 'mobile'])->where('is_primary', true)->count());
    }

    public function test_one_company_with_two_points_of_work_is_one_company_and_two_workshops(): void
    {
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $branch = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_branch')));

        $this->assertSame(1, WorkshopCompany::query()->count());
        $this->assertSame(2, Workshop::query()->count());
        $this->assertSame('Sanpetru', $branch->locality);
        $this->assertSame('approximate', $branch->geocode_status);
        $this->assertSame(25, $branch->coordinates_confidence);
    }

    public function test_an_itp_station_authorised_for_all_three_classes_is_read(): void
    {
        // "ITP_CLASS_1,ITP_CLASS_2,ITP_CLASS_3" is 35 characters. PostgreSQL refused it while the
        // column held 32, and 282 stations failed on the first production import.
        $record = $this->ingestRar('ITP', $this->rarPayload('itp_station', ['itpAuthClass' => ['ITP_CLASS_1', 'ITP_CLASS_2', 'ITP_CLASS_3']]));

        $this->assertSame('parsed', $record->fresh()->parse_status);
        $this->assertSame('ITP_CLASS_1,ITP_CLASS_2,ITP_CLASS_3', $this->workshopFor($record)->authorizations()->firstOrFail()->authorization_class);
    }

    public function test_an_itp_station_at_the_same_address_joins_the_service_workshop(): void
    {
        $service = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $itpRecord = $this->ingestRar('ITP', $this->rarPayload('itp_station'));
        $itp = $this->workshopFor($itpRecord);

        $this->assertSame($service->id, $itp->id);
        $this->assertSame('rar_company_address', $itpRecord->link->match_type);
        $this->assertTrue($itp->is_itp);
        $this->assertTrue($itp->is_rar_authorized);
        $this->assertSame(2, $itp->authorizations()->count());
        $this->assertFalse($itp->capabilities()->where('capability', 'itp_4x4')->firstOrFail()->value);
    }

    public function test_a_renewed_authorisation_stays_the_same_workshop(): void
    {
        $first = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $renewal = $this->ingestRar('SERVICE', $this->rarPayload('service_awd', ['exitNo' => 'OCS.BV.NI.900099', 'revisionNo' => 4]));

        $this->assertSame($first->id, $this->workshopFor($renewal)->id);
        $this->assertSame('rar_identity', $renewal->link->match_type);
        $this->assertSame(2, $first->authorizations()->count());
    }

    public function test_a_brasov_workshop_the_registry_placed_in_bucharest_gets_no_point(): void
    {
        $record = $this->ingestRar('SERVICE', $this->rarPayload('service_awd', ['branch' => ['address' => ['gpsLocation' => '44.4684269,26.0495798']]]));
        $workshop = $this->workshopFor($record);

        $this->assertNull($workshop->latitude);
        $this->assertSame('pending', $workshop->geocode_status);
        $this->assertSame('44.4684269,26.0495798', $record->payload['branch']['address']['gpsLocation']);
    }

    public function test_b4_components_are_kept_as_their_own_codes(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('B4', $this->rarPayload('b4_winch')));

        $winch = WorkshopAuthorizationActivity::query()->where('code', 'B4_1_3_C40')->firstOrFail();
        $this->assertSame('B4.1.3 · C40', $winch->display_code);
        $this->assertStringContainsString('trolii', $winch->description);
        $this->assertTrue($workshop->is_modification_authorized);
        $this->assertFalse($workshop->is_rar_authorized);
        $this->assertContains('winch_installation', $this->services($workshop, WorkshopEvidenceType::RarAuthorization));
        $this->assertSame(25, $workshop->offroad_score);
    }

    public function test_reading_a_record_twice_leaves_everything_as_it_was(): void
    {
        $record = $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $before = [WorkshopAuthorizationActivity::query()->count(), WorkshopContact::query()->count(), WorkshopService::query()->count(), Workshop::query()->count()];

        $this->assertTrue(app(RecordNormalizers::class)->normalizeSafely($record->fresh()));

        $this->assertSame($before, [WorkshopAuthorizationActivity::query()->count(), WorkshopContact::query()->count(), WorkshopService::query()->count(), Workshop::query()->count()]);
    }

    public function test_a_record_that_cannot_be_read_says_why(): void
    {
        $result = app(SourceRecordStore::class)->store(
            WorkshopDataSource::forKey('rar_service'),
            new SourceRecordData('authorization', 'SERVICE:broken', ['branch' => ['address' => ['county' => 'Cluj']]]),
        );

        $this->assertFalse(app(RecordNormalizers::class)->normalizeSafely($result->record));
        $this->assertSame('failed', $result->record->fresh()->parse_status);
        $this->assertStringContainsString('organisation', $result->record->fresh()->parse_error);
    }
}

/** Keeps the contact assertions readable: RAR landlines are stored as the "phone" type. */
final class PhoneFixture
{
    public static function type(string $type): string
    {
        return $type === 'phone' ? 'landline' : $type;
    }
}
