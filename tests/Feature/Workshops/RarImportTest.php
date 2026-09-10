<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopImportStatus;
use App\Models\Workshop;
use App\Models\WorkshopCompany;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Rar\RarNomenclature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class RarImportTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    private function hopeSped(): array
    {
        return $this->rarPayload('service_branch', [
            'exitNo' => 'OCS.CV.NI.900003',
            'auditFileNo' => 'CV9003',
            'branch' => [
                'organisationInfo' => ['taxRegisterNo' => '15428073', 'name' => '"HOPE SPED" SRL', 'address' => ['county' => 'Covasna', 'city' => 'Sfantu Gheorghe', 'originalAddress' => 'STR. ȚIGARETEI NR. 57, SFANTU GHEORGHE, JUD. COVASNA']],
                'address' => ['county' => 'Covasna', 'city' => 'Ozun', 'originalAddress' => 'SAT OZUN, COMUNA OZUN, DJ103B NR. 1', 'gpsLocation' => '45.79,25.85'],
                'telephoneNo' => '0733 000 303',
            ],
        ]);
    }

    private function import(array $options = []): WorkshopImportRun
    {
        $this->artisan('workshops:rar:import', ['--sync' => true] + $options)->assertSuccessful();

        return WorkshopImportRun::query()->latest('id')->firstOrFail();
    }

    public function test_a_national_import_creates_one_workshop_per_point_of_work(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd'), $this->rarPayload('service_branch')], 'Covasna' => [$this->hopeSped()]]);

        $run = $this->import();

        $this->assertSame(WorkshopImportStatus::Completed, $run->status);
        $this->assertSame(3, $run->discovered_count);
        $this->assertSame(3, $run->created_count);
        $this->assertSame(3, WorkshopSourceRecord::query()->where('record_type', 'authorization')->count());
        $this->assertSame(3, Workshop::query()->count());
        $this->assertSame(2, WorkshopCompany::query()->count());
        $this->assertSame(2, WorkshopCompany::query()->where('cui', '20963285')->firstOrFail()->workshops()->count());
        $this->assertEqualsCanonicalizing(['Brasov', 'Covasna'], $run->completedPartitions());
        $this->assertSame(0, WorkshopSourceRecord::query()->where('parse_status', '!=', 'parsed')->where('record_type', 'authorization')->count());
    }

    public function test_running_the_same_import_twice_changes_nothing(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd'), $this->rarPayload('service_branch')]]);

        $this->import();
        $counts = [Workshop::query()->count(), WorkshopContact::query()->count(), WorkshopSourceRecord::query()->count()];
        $second = $this->import();

        $this->assertSame(0, $second->created_count);
        $this->assertSame(0, $second->updated_count);
        $this->assertSame(2, $second->unchanged_count);
        $this->assertSame($counts, [Workshop::query()->count(), WorkshopContact::query()->count(), WorkshopSourceRecord::query()->count()]);
    }

    public function test_a_changed_record_is_stored_again_and_read_again(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')]]);
        $this->import();

        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd', ['branch' => ['telephoneNo' => '0744 999 111']])]]);
        $second = $this->import();

        $this->assertSame(1, $second->updated_count);
        $this->assertTrue(WorkshopContact::query()->where('normalized_value', '+40744999111')->exists());
        $this->assertSame(1, Workshop::query()->count());
    }

    public function test_a_request_that_fails_once_is_retried(): void
    {
        $calls = 0;
        Http::fake([
            '*/public/address/regions/RO' => Http::response(['Brasov']),
            '*/public/RarPublicAuthorizations/*' => function (Request $request) use (&$calls) {
                return ++$calls === 1 ? Http::response('busy', 503) : Http::response([$this->rarPayload('service_awd')]);
            },
        ]);

        $run = $this->import();

        $this->assertSame(2, $calls);
        $this->assertSame(WorkshopImportStatus::Completed, $run->status);
        $this->assertSame(1, Workshop::query()->count());
    }

    public function test_a_county_that_keeps_failing_is_recorded_and_can_be_resumed(): void
    {
        $this->fakeRegistry(['Brasov' => 500, 'Covasna' => [$this->hopeSped()]]);

        $run = $this->import();

        $this->assertSame(WorkshopImportStatus::CompletedWithErrors, $run->status);
        $this->assertSame(['Brasov'], $run->failedPartitions());
        $this->assertSame(['Covasna'], $run->completedPartitions());
        $this->assertSame(1, Workshop::query()->count());

        $covasnaRequests = fn (): int => Http::recorded(fn (Request $request) => str_contains($request->url(), 'county=Covasna'))->count();
        $covasnaBefore = $covasnaRequests();
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')], 'Covasna' => [$this->hopeSped()]]);
        $resumed = $this->import(['--resume' => true]);

        $this->assertSame($run->id, $resumed->id);
        $this->assertSame(WorkshopImportStatus::Completed, $resumed->status);
        $this->assertSame(2, Workshop::query()->count());
        // The county that had finished is not fetched again.
        $this->assertSame($covasnaBefore, $covasnaRequests());
    }

    public function test_the_same_record_twice_in_one_answer_is_stored_once(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd'), $this->rarPayload('service_awd')]]);

        $run = $this->import();

        $this->assertSame(1, WorkshopSourceRecord::query()->where('record_type', 'authorization')->count());
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(1, Workshop::query()->count());
    }

    public function test_a_record_the_registry_stops_listing_is_retired_not_deleted(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd'), $this->rarPayload('service_branch')]]);
        $this->import();
        $branch = WorkshopSourceRecord::query()->where('external_id', 'SERVICE:OCS.BV.NI.900002')->firstOrFail();

        $this->travel(1)->minutes();
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')]]);
        $run = $this->import();

        $this->assertSame(1, $run->retired_count);
        $this->assertFalse($branch->fresh()->is_current);
        $workshop = $this->workshopFor($branch);
        $this->assertFalse($workshop->is_rar_authorized);
        $this->assertFalse($workshop->is_active);
        $this->assertFalse($workshop->authorizations()->firstOrFail()->is_current);
        $this->assertSame(2, Workshop::query()->count());
    }

    public function test_a_county_that_suddenly_returns_far_fewer_records_retires_nothing(): void
    {
        $rows = collect(range(1, 25))->map(fn (int $i): array => $this->rarPayload('service_awd', ['exitNo' => "OCS.BV.NI.99{$i}", 'auditFileNo' => "BV8{$i}", 'branch' => ['address' => ['streetNo' => (string) $i, 'originalAddress' => "STR. TEST NR. {$i}, BRAȘOV"]]]))->all();
        $this->fakeRegistry(['Brasov' => $rows]);
        $this->import();

        $this->travel(1)->minutes();
        $this->fakeRegistry(['Brasov' => array_slice($rows, 0, 5)]);
        $run = $this->import();

        $this->assertSame(0, $run->retired_count);
        $this->assertSame(25, WorkshopSourceRecord::query()->where('is_current', true)->where('record_type', 'authorization')->count());
        $this->assertArrayHasKey('Brasov', $run->fresh()->metadata['retire_skipped']);
    }

    public function test_limited_and_dry_runs_retire_nothing_and_a_dry_run_stores_nothing(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd'), $this->rarPayload('service_branch')]]);

        $this->import(['--dry-run' => true]);
        $this->assertSame(0, WorkshopSourceRecord::query()->where('record_type', 'authorization')->count());

        $this->import(['--limit' => 1]);
        $this->assertSame(1, WorkshopSourceRecord::query()->where('record_type', 'authorization')->count());

        $this->travel(1)->minutes();
        $this->import(['--limit' => 1]);
        $this->assertSame(1, WorkshopSourceRecord::query()->where('is_current', true)->count());
    }

    public function test_queued_imports_run_one_job_per_county_and_finish_the_run(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')], 'Covasna' => [$this->hopeSped()]]);

        $this->artisan('workshops:rar:import')->assertSuccessful();

        $run = WorkshopImportRun::query()->latest('id')->firstOrFail();
        $this->assertSame(WorkshopImportStatus::Completed, $run->status);
        $this->assertSame(2, Workshop::query()->count());
    }

    public function test_one_county_can_be_imported_by_its_code(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')], 'Covasna' => [$this->hopeSped()]]);

        $this->import(['--county' => 'BV']);

        $this->assertSame(['BV'], Workshop::query()->pluck('county_code')->all());
        $this->artisan('workshops:rar:import', ['--sync' => true, '--county' => 'Atlantis'])->assertFailed();
    }

    public function test_discovery_stores_the_county_list_and_the_nomenclature(): void
    {
        Http::fake([
            '*/public/address/regions/RO' => Http::response(['Brasov', 'Bucuresti', 'Caras-Severin']),
            '*/assets/i18n/ro.json' => Http::response(['RarAuthorizationActivitiesEnumBySection' => ['SERVICE' => ['A1' => 'A1 Activități de reparații (live)']]]),
        ]);

        $this->artisan('workshops:rar:discover')->assertSuccessful();

        $source = WorkshopDataSource::query()->where('key', DataSourceCatalog::rarKey('SERVICE'))->firstOrFail();
        $this->assertSame(['BV', 'B', 'CS'], array_column($source->stateValue('counties'), 'county_code'));
        $this->assertTrue(WorkshopSourceRecord::query()->where('record_type', 'nomenclature')->exists());
        $this->assertSame('A1 Activități de reparații (live)', app(RarNomenclature::class)->activity('SERVICE', 'A1'));
    }
}
