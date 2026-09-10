<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Sources\Rar\ActivityData;
use App\Workshops\Sources\Rar\RarAuthorizationParser;
use App\Workshops\Sources\Rar\RarNomenclature;
use App\Workshops\Sources\Rar\RarParseException;
use App\Workshops\Sources\Rar\RarRecordKeys;
use Tests\TestCase;

class RarAuthorizationParserTest extends TestCase
{
    private function parser(): RarAuthorizationParser
    {
        return new RarAuthorizationParser(new RarNomenclature([]));
    }

    private function payload(string $name, array $overrides = []): array
    {
        return array_replace_recursive(json_decode((string) file_get_contents(base_path("tests/Fixtures/Workshops/rar/{$name}.json")), true), $overrides);
    }

    /** @param list<ActivityData> $activities */
    private function activity(array $activities, string $code): ActivityData
    {
        foreach ($activities as $activity) {
            if ($activity->code === $code) {
                return $activity;
            }
        }

        $this->fail("Activity {$code} was not parsed.");
    }

    public function test_company_and_registered_office(): void
    {
        $data = $this->parser()->parse('SERVICE', $this->payload('service_awd'));

        $this->assertSame('AUTO TEHNIC ȘERBAN S.R.L.', $data->company->legalName);
        $this->assertSame('20963285', $data->company->cui);
        $this->assertSame('J08/346/2007', $data->company->registrationNumber);
        $this->assertTrue($data->company->isVatPayer);
        $this->assertSame('STR. ȚESĂTORILOR NR. 12, BRAȘOV, JUD. BRAȘOV', $data->company->registeredOffice->address);
        $this->assertSame(['office@autotehnicserban.ro'], $data->company->emails);
        $this->assertSame(['+40268000111'], array_map(fn ($phone) => $phone->e164, $data->company->phones));
    }

    public function test_point_of_work_is_the_workshop_address_not_the_office(): void
    {
        $location = $this->parser()->parse('SERVICE', $this->payload('service_awd'))->location;

        $this->assertSame('ȘOS. CRISTIANULUI NR. 6 (HALA REPARAȚII), BRAȘOV, JUDEȚUL BRAȘOV', $location->address);
        $this->assertSame('Brașov', $location->locality);
        $this->assertSame('BV', $location->countyCode);
        $this->assertSame('500130', $location->postalCode);
        $this->assertSame('precise', $location->coordinateQuality);
        $this->assertSame(45.6624831, $location->latitude);
        $this->assertSame(60, $location->coordinateConfidence);
    }

    public function test_every_phone_is_kept_once(): void
    {
        $phones = $this->parser()->parse('SERVICE', $this->payload('service_awd'))->phones;

        $this->assertSame(['+40722000101', '+40268000102'], array_map(fn ($phone) => $phone->e164, $phones));
        $this->assertSame('0722 000 101', $phones[0]->raw);
    }

    public function test_activity_codes_keep_their_hierarchy_and_published_wording(): void
    {
        $data = $this->parser()->parse('SERVICE', $this->payload('service_awd'));
        $awd = $this->activity($data->activities, 'A1_2_1_3');

        $this->assertSame('A1.2.1.3', $awd->displayCode());
        $this->assertSame('A1_2_1', $awd->parentCode);
        $this->assertSame('A1_2', $awd->entryCode);
        $this->assertSame(3, $awd->depth);
        $this->assertStringStartsWith('A1.2.1.3.', $awd->rawDescription);
        $this->assertStringContainsString('tracțiune permanentă pe mai multe axe', $awd->description());
        $this->assertEqualsCanonicalizing(['M1', 'N1', 'N2'], $awd->vehicleCategories);
        $this->assertSame(['În funcție de aplicabilitatea tehnică*'], $awd->limitations);
    }

    public function test_multiple_activities_are_each_their_own_row(): void
    {
        $codes = array_map(fn ($activity) => $activity->code, $this->parser()->parse('SERVICE', $this->payload('service_awd'))->activities);

        $this->assertCount(count(array_unique($codes)), $codes);
        $this->assertContains('A1', $codes);
        $this->assertContains('A1_1_3', $codes);
        $this->assertContains('A1_3_2_1', $codes);
        $this->assertContains('A3_1', $codes);
        $this->assertCount(24, $codes);
    }

    public function test_limitations_remarks_and_suspensions_in_force(): void
    {
        $data = $this->parser()->parse('SERVICE', $this->payload('service_awd'));

        $this->assertSame(['Gmax = 5,5 t'], $this->activity($data->activities, 'A1')->limitations);
        $this->assertSame(['Geometrie doar pentru M1, N1'], $this->activity($data->activities, 'A3_1')->observations);
        $this->assertSame('SUSPENSION', $this->activity($data->activities, 'A3_1')->restrictions[0]['code']);
        $this->assertStringContainsString('OCS.BV.SA.000001', $this->activity($data->activities, 'A3_1')->restrictions[0]['text']);
        $this->assertSame([], $this->activity($data->activities, 'A1')->restrictions);
    }

    public function test_authorisation_details_and_local_dates(): void
    {
        $data = $this->parser()->parse('SERVICE', $this->payload('service_awd'));

        $this->assertSame('90001', $data->number);
        $this->assertSame('OCS.BV.NI.900001', $data->exitNumber);
        $this->assertSame('BV9001', $data->auditFileNumber);
        $this->assertSame('CLASS_2,CLASS_3', $data->authorizationClass);
        $this->assertSame('2026-12-31', $data->validUntil->toDateString());
        // 21:00 UTC on 14 June is midnight on 15 June in Bucharest.
        $this->assertSame('2009-06-15', $data->initiallyAuthorizedAt->toDateString());
        $this->assertSame(6, $data->workstations);
        $this->assertSame(['DACIA', 'RENAULT'], $data->brands);
        $this->assertArrayNotHasKey('branch', $data->summary);
        $this->assertArrayHasKey('legalFrameworks', $data->summary);
    }

    public function test_itp_classes_carry_their_limitations_and_interdictions(): void
    {
        $data = $this->parser()->parse('ITP', $this->payload('itp_station'));
        $class = $this->activity($data->activities, 'ITP_CLASS_2');

        $this->assertSame('BV901', $data->stationCode);
        $this->assertSame('ITP', $class->parentCode);
        $this->assertStringContainsString('3.500 kg', (string) $class->rawDescription);
        $this->assertSame(['Distanță minimă între fețele interioare ale anvelopelor dmin=1.02 m'], $class->limitations);
        $this->assertContains('ITP_INTERDICTION_AUTO_PERMANENT_ALLWHEEL', array_column($class->restrictions, 'code'));
        $this->assertContains('Autovehicule echipate cu tracțiune integrală permanentă', array_column($class->restrictions, 'text'));
        $this->assertNotEmpty($class->observations);
    }

    public function test_b4_components_become_rows_under_their_activity(): void
    {
        $data = $this->parser()->parse('B4', $this->payload('b4_winch'));
        $winch = $this->activity($data->activities, 'B4_1_3_C40');

        $this->assertSame('B4.1.3 · C40', $winch->displayCode());
        $this->assertSame('B4_1_3', $winch->parentCode);
        $this->assertStringContainsString('trolii', (string) $winch->rawDescription);
        $this->assertSame(['Masa max = 3,5 t'], $winch->limitations);
        $this->assertSame('CLASS_1', $data->authorizationClass);
        $this->assertStringContainsString('C9 – Dispozitive de protecție frontală', implode(' ', $this->activity($data->activities, 'B4_1_3')->observations));
    }

    public function test_town_level_points_are_approximate_and_wrong_ones_are_dropped(): void
    {
        $approximate = $this->parser()->parse('SERVICE', $this->payload('service_branch'))->location;
        $this->assertSame('approximate', $approximate->coordinateQuality);
        $this->assertSame(25, $approximate->coordinateConfidence);

        $inBucharest = $this->parser()->parse('SERVICE', $this->payload('service_awd', ['branch' => ['address' => ['gpsLocation' => '44.4684269,26.0495798']]]))->location;
        $this->assertSame('implausible', $inBucharest->coordinateQuality);
        $this->assertNull($inBucharest->latitude);
        $this->assertSame('44.4684269,26.0495798', $inBucharest->rawCoordinates);

        $officePoint = $this->parser()->parse('SERVICE', $this->payload('service_branch', ['branch' => ['address' => ['gpsLocation' => '45.65,25.60']]]))->location;
        $this->assertSame('registered_office', $officePoint->coordinateQuality);
        $this->assertNull($officePoint->latitude);
    }

    public function test_a_record_with_almost_nothing_in_it_still_reads(): void
    {
        $data = $this->parser()->parse('SERVICE', ['branch' => ['name' => 'ATELIER MINIM SRL, STR. X', 'address' => ['county' => 'Cluj']], 'authorizedActivities' => [['activity' => 'A2']]]);

        $this->assertSame('ATELIER MINIM SRL', $data->company->legalName);
        $this->assertNull($data->company->cui);
        $this->assertSame('CJ', $data->location->countyCode);
        $this->assertSame('missing', $data->location->coordinateQuality);
        $this->assertSame([], $data->phones);
        $this->assertSame(['A2'], array_map(fn ($a) => $a->code, $data->activities));
    }

    public function test_a_record_without_any_name_is_refused(): void
    {
        $this->expectException(RarParseException::class);

        $this->parser()->parse('SERVICE', ['branch' => ['address' => ['county' => 'Cluj']]]);
    }

    public function test_records_are_identified_by_document_and_workshops_by_audit_file(): void
    {
        $service = $this->payload('service_awd');
        $itp = $this->payload('itp_station');

        $this->assertSame('SERVICE:OCS.BV.NI.900001', RarRecordKeys::externalId('SERVICE', $service));
        $this->assertSame('SERVICE:BV9001:20963285', RarRecordKeys::identityKey('SERVICE', $service));
        $this->assertSame('ITP:BV901', RarRecordKeys::identityKey('ITP', $itp));
        $this->assertSame('SERVICE:90001|BV9001|2025-12-24T00:00:00.000+0000|20963285', RarRecordKeys::externalId('SERVICE', ['exitNo' => null] + $service));
    }
}
