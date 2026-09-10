<?php

namespace Tests\Feature\Workshops;

use App\Models\WorkshopDataSource;
use App\Workshops\Export\WorkshopExporter;
use App\Workshops\Search\QueryInterpreter;
use App\Workshops\Search\WorkshopFilters;
use App\Workshops\Search\WorkshopSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class SearchAndExportTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $this->ingestRar('SERVICE', $this->rarPayload('service_branch'));
        $this->ingestRar('B4', $this->rarPayload('b4_winch'));
    }

    private function names(WorkshopFilters $filters): array
    {
        return app(WorkshopSearch::class)->query($filters)->pluck('locality')->all();
    }

    public function test_plain_phrases_are_read_into_filters(): void
    {
        $interpreter = app(QueryInterpreter::class);

        $gearboxes = $interpreter->interpret('service cutii automate Brașov')['filters'];
        $this->assertSame('BV', $gearboxes->county);
        $this->assertSame('automatic_transmission', $gearboxes->service);
        $this->assertNull($gearboxes->text);

        $itp = $interpreter->interpret('ITP 4x4 Iași')['filters'];
        $this->assertTrue($itp->itp);
        $this->assertSame(['itp_4x4'], $itp->capabilities);
        $this->assertSame('IS', $itp->county);

        $offroad = $interpreter->interpret('suspensii offroad București')['filters'];
        $this->assertSame('B', $offroad->county);
        $this->assertSame('offroad', $offroad->sort);

        $fourByFour = $interpreter->interpret('service 4x4 Cluj')['filters'];
        $this->assertSame(['4x4'], $fourByFour->capabilities);
        $this->assertSame('CJ', $fourByFour->county);

        $town = $interpreter->interpret('diagnoza sanpetru')['filters'];
        $this->assertSame('sanpetru', $town->locality);
    }

    public function test_filters_narrow_the_registry(): void
    {
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(county: 'BV', service: 'automatic_transmission')));
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(capabilities: ['4x4'])));
        $this->assertSame(['Cluj-Napoca'], $this->names(new WorkshopFilters(authorizationCode: 'B4.1.3')));
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(authorizationCode: 'A1.2.1.3')));
        $this->assertCount(2, $this->names(new WorkshopFilters(rar: true)));
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(hasEmail: true)));
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(text: 'Cristianului')));
        $this->assertSame(['Cluj-Napoca'], $this->names(new WorkshopFilters(text: '15428073')));
        $this->assertSame(['Brașov'], $this->names(new WorkshopFilters(text: '0268 000 102')));
    }

    public function test_a_radius_keeps_nearby_workshops_only(): void
    {
        $near = $this->names(new WorkshopFilters(latitude: 45.66, longitude: 25.57, radiusKm: 20));

        $this->assertContains('Brașov', $near);
        $this->assertNotContains('Cluj-Napoca', $near);
    }

    public function test_the_csv_export_opens_in_a_spreadsheet_with_diacritics_intact(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'workshops');

        $rows = app(WorkshopExporter::class)->export(new WorkshopFilters(county: 'BV'), 'csv', $path);

        $content = (string) file_get_contents($path);
        $this->assertSame(2, $rows);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('AUTO TEHNIC ȘERBAN S.R.L.', $content);
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", substr($content, 3)))));
        $this->assertSame(WorkshopExporter::COLUMNS, $lines[0]);
        $awd = collect($lines)->first(fn (array $line) => $line[8] === 'Brașov');
        $this->assertStringContainsString('A1.2.1.3', $awd[array_search('coduri_activitati_rar', WorkshopExporter::COLUMNS, true)]);
        $this->assertSame('da', $awd[array_search('suporta_4x4', WorkshopExporter::COLUMNS, true)]);
    }

    public function test_the_export_command_writes_json_with_filters(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'workshops').'.json';

        $this->artisan('workshops:export', ['--format' => 'json', '--output' => $path, '--capability' => ['4x4'], '--has-email' => true])->assertSuccessful();

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertCount(1, $data);
        $this->assertSame('20963285', $data[0]['cui']);
        $this->assertContains('office@autotehnicserban.ro', $data[0]['emailuri']);
        $this->assertContains('rar_service', $data[0]['surse']);
    }

    public function test_public_output_leaves_out_sources_not_cleared_for_publication(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'workshops');

        $this->assertSame(0, app(WorkshopExporter::class)->export(new WorkshopFilters, 'csv', $path, publicOnly: true));

        WorkshopDataSource::query()->where('key', 'rar_service')->update(['is_public_output_allowed' => true]);
        $this->assertSame(2, app(WorkshopExporter::class)->export(new WorkshopFilters, 'csv', $path, publicOnly: true));
    }
}
