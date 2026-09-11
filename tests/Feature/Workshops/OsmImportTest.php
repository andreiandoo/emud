<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopEvidenceType;
use App\Models\Workshop;
use App\Models\WorkshopContact;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopImportRun;
use App\Models\WorkshopSourceLink;
use App\Models\WorkshopSourceRecord;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Sources\Osm\OsmImporter;
use App\Workshops\Sources\Osm\OsmiumExtractor;
use App\Workshops\Sources\Osm\OsmPoiParser;
use App\Workshops\Sources\Osm\OsmWorkshopMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class OsmImportTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    private function importOsm(?string $file = null): array
    {
        $run = WorkshopImportRun::start(WorkshopDataSource::forKey(DataSourceCatalog::OSM));

        return app(OsmImporter::class)->import($file ?? $this->fixture('osm/workshops.geojsonseq'), $run);
    }

    private function osmWorkshop(string $reference): ?Workshop
    {
        return WorkshopSourceRecord::query()->where('external_id', $reference)->first()?->link?->workshop;
    }

    public function test_a_mapped_workshop_with_the_same_phone_is_the_rar_workshop(): void
    {
        $rar = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));

        $counts = $this->importOsm();

        $this->assertSame(3, $counts['seen']);
        $this->assertSame(1, $counts['skipped']);
        $this->assertSame($rar->id, $this->osmWorkshop('node/1001')->id);
        $this->assertSame('osm_phone', WorkshopSourceLink::query()->whereHas('sourceRecord', fn ($q) => $q->where('external_id', 'node/1001'))->firstOrFail()->match_type);

        $rar->refresh();
        $this->assertSame('osm', $rar->coordinates_source);
        $this->assertSame(85, $rar->coordinates_confidence);
        $this->assertTrue(WorkshopContact::query()->where('workshop_id', $rar->id)->where('type', 'website')->exists());
        $this->assertSame('osm', WorkshopWebsiteCandidate::query()->where('workshop_id', $rar->id)->firstOrFail()->discovered_via);
        $this->assertTrue($rar->services()->where('evidence_type', WorkshopEvidenceType::Osm->value)->exists());
        $this->assertTrue($rar->services()->where('evidence_type', WorkshopEvidenceType::RarAuthorization->value)->exists());
    }

    public function test_a_neighbour_thirty_metres_away_is_not_merged_for_being_close(): void
    {
        $rar = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));

        $this->importOsm();

        $tyres = $this->osmWorkshop('way/2002');
        $this->assertNotNull($tyres);
        $this->assertNotSame($rar->id, $tyres->id);
        $this->assertSame('Vulcanizare Nord', $tyres->name);
        $this->assertNull($tyres->company_id);
        $this->assertSame('BV', $tyres->county_code);
        $this->assertSame(75, $tyres->coordinates_confidence);
    }

    public function test_an_unknown_mapped_workshop_becomes_a_new_workshop_with_what_osm_says(): void
    {
        $this->importOsm();

        $garage = $this->osmWorkshop('node/3003');
        $this->assertSame('CJ', $garage->county_code);
        $this->assertTrue($garage->supports_4x4);
        $this->assertSame('osm', $garage->capabilities()->where('capability', '4x4')->firstOrFail()->basis);
        $this->assertSame('https://www.facebook.com/offroadgaragecluj', WorkshopContact::query()->where('workshop_id', $garage->id)->where('type', 'facebook')->firstOrFail()->value);
        $this->assertFalse($garage->is_rar_authorized);
    }

    public function test_importing_the_same_extract_twice_changes_nothing_and_a_vanished_point_is_retired(): void
    {
        $this->importOsm();
        $workshops = Workshop::query()->count();

        $second = $this->importOsm();
        $this->assertSame(0, $second['created']);
        $this->assertSame($workshops, Workshop::query()->count());

        $this->travel(1)->minutes();
        $partial = tempnam(sys_get_temp_dir(), 'osm');
        file_put_contents($partial, implode("\n", array_slice(explode("\n", (string) file_get_contents($this->fixture('osm/workshops.geojsonseq'))), 0, 2)));
        $third = $this->importOsm($partial);

        $this->assertSame(1, $third['retired']);
        $this->assertFalse($this->osmWorkshop('node/3003')->is_active);
    }

    public function test_a_website_or_a_name_at_the_same_address_is_also_a_match(): void
    {
        $rar = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        WorkshopContact::query()->create(['workshop_id' => $rar->id, 'type' => 'website', 'value' => 'https://autotehnicserban.ro', 'normalized_value' => 'autotehnicserban.ro', 'confidence_score' => 80, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        $parser = app(OsmPoiParser::class);

        // Mapped 300 m off the registry's point, under a name nobody would recognise: the website
        // is what identifies it.
        $byWebsite = app(OsmWorkshopMatcher::class)->match($parser->parse(['type' => 'node', 'id' => 9, 'lat' => 45.6650, 'lng' => 25.5712, 'tags' => ['name' => 'Alt nume', 'website' => 'https://www.autotehnicserban.ro/contact', 'addr:city' => 'Brașov']]));
        $this->assertSame($rar->id, $byWebsite->targetId);
        $this->assertSame('website', $byWebsite->method);

        $byAddress = app(OsmWorkshopMatcher::class)->match($parser->parse(['type' => 'node', 'id' => 10, 'lat' => 45.6625, 'lng' => 25.5713, 'tags' => ['name' => 'Auto Tehnic Serban', 'addr:street' => 'Șoseaua Cristianului', 'addr:housenumber' => '6', 'addr:city' => 'Brașov']]));
        $this->assertTrue($byAddress->isLinked());
        $this->assertSame($rar->id, $byAddress->targetId);
    }

    public function test_osmium_is_asked_for_the_workshop_tags_only(): void
    {
        Process::fake();

        $output = app(OsmiumExtractor::class)->extract('/data/osm/romania-latest.osm.pbf');

        $this->assertSame('/data/osm/workshops.geojsonseq', $output);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command) && in_array('tags-filter', $process->command, true) && in_array('nwr/shop=car_repair', $process->command, true));
        // A closed way would otherwise be written twice, as a line and as an area, under one id.
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command) && in_array('geojsonseq', $process->command, true) && in_array('--geometry-types=point,polygon', $process->command, true));
    }
}
