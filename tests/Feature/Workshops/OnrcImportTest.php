<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopCompany;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Onrc\CaretSeparatedReader;
use App\Workshops\Sources\Onrc\OnrcDatasetLocator;
use App\Workshops\Sources\Onrc\OnrcImporter;
use App\Workshops\Sources\Onrc\OnrcRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class OnrcImportTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    private function files(): array
    {
        return collect(['OD_FIRME.CSV', 'OD_CAEN_AUTORIZAT.CSV', 'OD_STARE_FIRMA.CSV', 'N_STARE_FIRMA.CSV', 'N_CAEN.CSV'])
            ->mapWithKeys(fn (string $name): array => [$name => ['path' => $this->fixture('onrc/'.strtolower($name))]])
            ->all();
    }

    private function importOnrc(): array
    {
        $release = new OnrcRelease('firme-02-09-2026', 'Firme înregistrate până la 02.09.2026', null, ['OD_FIRME.CSV' => ['id' => 'r1', 'url' => 'https://data.gov.ro/od_firme.csv', 'size' => null, 'last_modified' => null]]);
        $run = WorkshopImportRun::start(WorkshopDataSource::forKey(DataSourceCatalog::ONRC));

        return app(OnrcImporter::class)->import($release, $this->files(), $run);
    }

    public function test_the_reader_streams_caret_files_with_a_bom_quotes_and_broken_lines(): void
    {
        $rows = iterator_to_array(CaretSeparatedReader::rows($this->fixture('onrc/od_firme.csv')), false);

        $this->assertCount(6, $rows);
        $this->assertSame('AUTO TEHNIC ŞERBAN SRL', $rows[0]['DENUMIRE']);
        $this->assertSame('"HOPE SPED" SRL', $rows[1]['DENUMIRE']);
        $this->assertSame('cu o linie ruptă în două', $rows[5]['ADR_COMPLETARE']);
    }

    public function test_a_company_known_from_rar_gets_its_legal_identity_confirmed(): void
    {
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));

        $counts = $this->importOnrc();

        $company = WorkshopCompany::query()->where('cui', '20963285')->firstOrFail();
        $this->assertNotNull($company->onrc_verified_at);
        $this->assertSame('AUTO TEHNIC ȘERBAN SRL', $company->legal_name);
        $this->assertSame('ROONRC.J08/346/2007', $company->euid);
        $this->assertSame('funcțiune', $company->status);
        $this->assertSame('1048', $company->status_code);
        $this->assertTrue($company->has_automotive_caen);
        $this->assertSame('http://www.autotehnicserban.ro', $company->website);
        $this->assertEqualsCanonicalizing(['4520', '4511'], array_column($company->caen_codes, 'code'));
        $this->assertSame(5, $counts['automotive']);
        $this->assertSame(4, $counts['kept']);
    }

    public function test_an_operating_repair_company_rar_does_not_list_becomes_a_lead_without_a_workshop(): void
    {
        $this->importOnrc();

        $lead = WorkshopCompany::query()->where('cui', '15428073')->firstOrFail();
        $this->assertSame('onrc', $lead->discovered_via);
        $this->assertSame('CV', $lead->county_code);
        $this->assertSame(0, $lead->workshops()->count());
        $this->assertTrue(WorkshopCompany::query()->where('legal_name', 'POPESCU ION PFA')->whereNull('cui')->exists());
    }

    public function test_struck_off_and_unrelated_companies_are_left_out(): void
    {
        $this->importOnrc();

        $this->assertFalse(WorkshopCompany::query()->where('cui', '44444440')->exists());
        $this->assertFalse(WorkshopCompany::query()->where('cui', '55555551')->exists());
        $this->assertFalse(WorkshopSourceRecord::query()->where('external_id', 'J40/1/2010')->exists());
    }

    public function test_every_decision_is_recorded_and_a_second_import_changes_nothing(): void
    {
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $this->importOnrc();

        $this->assertSame(WorkshopRecordMatchStatus::Matched, WorkshopRecordMatch::query()->whereHas('sourceRecord', fn ($q) => $q->where('external_id', 'J08/346/2007'))->firstOrFail()->status);
        $companies = WorkshopCompany::query()->count();

        $counts = $this->importOnrc();

        $this->assertSame(0, $counts['created']);
        $this->assertSame($companies, WorkshopCompany::query()->count());
    }

    public function test_files_fetched_by_hand_are_read_from_their_directory_and_left_there(): void
    {
        $directory = dirname($this->fixture('onrc/od_firme.csv'));
        Http::fake(['data.gov.ro/api/*' => Http::response(['result' => ['results' => [
            ['name' => 'nomenclatoare-02-09-2026', 'metadata_created' => '2026-09-03T09:42:46', 'resources' => [
                ['name' => 'N_STARE_FIRMA.CSV', 'url' => 'https://data.gov.ro/n_stare.csv', 'id' => 'n1'],
                ['name' => 'N_CAEN.CSV', 'url' => 'https://data.gov.ro/n_caen.csv', 'id' => 'n2'],
            ]],
            ['name' => 'firme-02-09-2026', 'title' => 'Firme 02.09.2026', 'metadata_created' => '2026-09-03T09:45:53', 'resources' => [
                ['name' => 'OD_FIRME.CSV', 'url' => 'https://data.gov.ro/od_firme.csv', 'id' => 'f1', 'size' => filesize($directory.'/od_firme.csv')],
                ['name' => 'OD_CAEN_AUTORIZAT.CSV', 'url' => 'https://data.gov.ro/od_caen.csv', 'id' => 'c1'],
                ['name' => 'OD_STARE_FIRMA.CSV', 'url' => 'https://data.gov.ro/od_stare.csv', 'id' => 's1'],
            ]],
        ]]])]);

        $this->artisan('workshops:onrc:import', ['--sync' => true, '--from' => $directory])->assertSuccessful();

        $this->assertTrue(WorkshopCompany::query()->where('cui', '15428073')->exists());
        $this->assertSame('firme-02-09-2026', WorkshopDataSource::forKey(DataSourceCatalog::ONRC)->stateValue('release.key'));
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '.csv'));
        $this->assertFileExists($directory.'/od_firme.csv');
        $this->artisan('workshops:onrc:import', ['--sync' => true, '--from' => $directory.'/missing'])->assertFailed();
    }

    public function test_the_newest_release_is_found_through_the_open_data_api(): void
    {
        Http::fake(['data.gov.ro/*' => Http::response(['result' => ['results' => [
            ['name' => 'nomenclatoare-02-09-2026', 'metadata_created' => '2026-09-03T09:42:46', 'resources' => [['name' => 'N_STARE_FIRMA.CSV', 'url' => 'https://data.gov.ro/n_stare.csv', 'id' => 'n1']]],
            ['name' => 'firme-02-09-2026', 'title' => 'Firme 02.09.2026', 'metadata_created' => '2026-09-03T09:45:53', 'resources' => [
                ['name' => 'OD_FIRME.CSV', 'url' => 'https://data.gov.ro/od_firme.csv', 'id' => 'f1', 'size' => 693671250],
                ['name' => 'OD_CAEN_AUTORIZAT.CSV', 'url' => 'https://data.gov.ro/od_caen.csv', 'id' => 'c1'],
                ['name' => 'OD_STARE_FIRMA.CSV', 'url' => 'https://data.gov.ro/od_stare.csv', 'id' => 's1'],
            ]],
            ['name' => 'firme-08-07-2026', 'metadata_created' => '2026-07-08T10:59:36', 'resources' => []],
        ]]])]);

        $release = app(OnrcDatasetLocator::class)->latest();

        $this->assertSame('firme-02-09-2026', $release->key);
        $this->assertSame(693671250, $release->resource('OD_FIRME.CSV')['size']);
        $this->assertSame('https://data.gov.ro/n_stare.csv', $release->resource('N_STARE_FIRMA.CSV')['url']);
    }
}
