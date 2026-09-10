<?php

namespace Tests\Feature\Workshops;

use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class WorkshopCommandsTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    public function test_status_and_statistics_report_the_registry(): void
    {
        $this->ingestRar('SERVICE', $this->rarPayload('service_awd'));
        $this->ingestRar('ITP', $this->rarPayload('itp_station'));

        $this->artisan('workshops:status')->expectsOutputToContain('Normalized workshops')->assertSuccessful();
        $this->artisan('workshops:rar:stats')->expectsOutputToContain('Permanent all-wheel drive authorised')->assertSuccessful();
    }

    public function test_classification_rebuilds_derived_data_from_what_is_stored(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $workshop->capabilities()->delete();
        $workshop->forceFill(['supports_4x4' => null])->save();

        $this->artisan('workshops:classify', ['--sync' => true])->assertSuccessful();

        $this->assertTrue($workshop->fresh()->supports_4x4);
        $this->assertSame(1, Workshop::query()->count());
    }

    public function test_refresh_runs_the_registry_import_and_never_crawls_websites(): void
    {
        $this->fakeRegistry(['Brasov' => [$this->rarPayload('service_awd')]]);

        $this->artisan('workshops:refresh', ['--source' => ['rar'], '--sync' => true])->assertSuccessful();

        $this->assertSame(1, Workshop::query()->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'autotehnicserban'));
    }

    public function test_normalize_rereads_failed_records(): void
    {
        $this->artisan('workshops:normalize', ['--status' => 'failed', '--sync' => true])->expectsOutputToContain('No records')->assertSuccessful();
    }
}
