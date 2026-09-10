<?php

namespace Tests\Feature\Workshops;

use App\Enums\WorkshopEvidenceType;
use App\Models\WorkshopContact;
use App\Models\WorkshopService;
use App\Models\WorkshopSourceRecord;
use App\Models\WorkshopWebsiteCandidate;
use App\Workshops\Web\WebsiteCrawler;
use App\Workshops\Web\WebsiteDiscoverer;
use App\Workshops\Web\WebsiteEnricher;
use App\Workshops\Web\WebsiteExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class WebsiteEnrichmentTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
        config(['workshops.web.max_pages_per_site' => 4]);

        $html = fn (string $page) => Http::response((string) file_get_contents($this->fixture("web/{$page}.html")), 200, ['Content-Type' => 'text/html; charset=utf-8']);

        Http::fake([
            'https://www.autotehnicserban.ro/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n", 200, ['Content-Type' => 'text/plain']),
            'https://www.autotehnicserban.ro/contact' => $html('contact'),
            'https://www.autotehnicserban.ro/servicii' => $html('services'),
            'https://www.autotehnicserban.ro' => $html('home'),
            'https://www.autotehnicserban.ro/' => $html('home'),
            'https://www.listafirme.ro/*' => Http::response('<html>listing</html>', 200, ['Content-Type' => 'text/html']),
            '*' => Http::response('not found', 404),
        ]);
    }

    private function workshopWithSite(): array
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd')));
        $site = WorkshopWebsiteCandidate::query()->create([
            'workshop_id' => $workshop->id, 'url' => 'https://www.autotehnicserban.ro/', 'domain' => 'autotehnicserban.ro',
            'discovered_via' => 'osm', 'confidence' => 70, 'validation_status' => WorkshopWebsiteCandidate::PENDING,
        ]);

        return [$workshop, $site];
    }

    public function test_the_extractor_keeps_business_contacts_and_leaves_personal_ones(): void
    {
        $facts = app(WebsiteExtractor::class)->extract((string) file_get_contents($this->fixture('web/contact.html')), 'https://www.autotehnicserban.ro/contact', 'autotehnicserban.ro');

        $this->assertArrayHasKey('programari@autotehnicserban.ro', $facts['emails']);
        $this->assertSame(85, $facts['emails']['programari@autotehnicserban.ro']['confidence']);
        $this->assertSame(60, $facts['emails']['serban.service@gmail.com']['confidence']);
        $this->assertTrue(! isset($facts['emails']['maria.ionescu@altfirma.ro']) || $facts['emails']['maria.ionescu@altfirma.ro']['confidence'] === 0);
        $this->assertSame(['+40268000102'], array_map(fn ($p) => $p->e164, $facts['phones']));
        $this->assertSame(['+40722000101'], array_map(fn ($p) => $p->e164, $facts['whatsapp']));
        $this->assertSame(['https://www.instagram.com/autotehnicserban/'], $facts['instagram']);
    }

    public function test_structured_data_on_the_home_page_is_read(): void
    {
        $facts = app(WebsiteExtractor::class)->extract((string) file_get_contents($this->fixture('web/home.html')), 'https://www.autotehnicserban.ro/', 'autotehnicserban.ro');

        $this->assertSame(['Auto Tehnic Șerban'], $facts['names']);
        $this->assertSame(['lat' => 45.66252, 'lng' => 25.5713], $facts['geo']);
        $this->assertContains('+40722000101', array_map(fn ($p) => $p->e164, $facts['phones']));
        $this->assertArrayHasKey('office@autotehnicserban.ro', $facts['emails']);
        $this->assertContains('https://www.facebook.com/autotehnicserban', $facts['facebook']);
        $this->assertStringNotContainsString('display: none', $facts['text']);
    }

    public function test_the_crawler_stays_on_the_site_obeys_robots_and_stops_at_its_limit(): void
    {
        $pages = app(WebsiteCrawler::class)->crawl('https://www.autotehnicserban.ro/', 1);

        $this->assertEqualsCanonicalizing(
            ['https://www.autotehnicserban.ro/', 'https://www.autotehnicserban.ro/servicii', 'https://www.autotehnicserban.ro/contact'],
            array_map(fn ($page) => $page->url, $pages),
        );
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/admin'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'alt-site.ro'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '.pdf'));
        $this->assertSame(3, WorkshopSourceRecord::query()->where('record_type', 'page')->count());
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', config('workshops.user_agent')));
    }

    public function test_a_candidate_whose_pages_carry_the_workshop_phone_is_accepted(): void
    {
        [$workshop, $site] = $this->workshopWithSite();
        WorkshopWebsiteCandidate::query()->create(['workshop_id' => $workshop->id, 'url' => 'https://www.listafirme.ro/auto-tehnic', 'domain' => 'listafirme.ro', 'discovered_via' => 'search', 'confidence' => 20]);

        $result = app(WebsiteDiscoverer::class)->discover($workshop);

        $this->assertSame('https://www.autotehnicserban.ro/', $result['accepted']);
        $this->assertFalse($result['searched']);
        $this->assertSame(WorkshopWebsiteCandidate::ACCEPTED, $site->fresh()->validation_status);
        $this->assertTrue($site->fresh()->evidence['signals']['phone']);
        $this->assertTrue($site->fresh()->evidence['signals']['cui']);
        $this->assertSame(WorkshopWebsiteCandidate::REJECTED, WorkshopWebsiteCandidate::query()->where('domain', 'listafirme.ro')->firstOrFail()->validation_status);
        $this->assertSame('found', $workshop->fresh()->website_status);
    }

    public function test_reading_the_site_adds_contacts_with_their_page_and_declared_services(): void
    {
        [$workshop, $site] = $this->workshopWithSite();
        $site->update(['validation_status' => WorkshopWebsiteCandidate::ACCEPTED]);

        $counts = app(WebsiteEnricher::class)->enrich($workshop, $site->fresh());

        $this->assertSame(3, $counts['pages']);
        $email = WorkshopContact::query()->where('workshop_id', $workshop->id)->where('value', 'programari@autotehnicserban.ro')->firstOrFail();
        $this->assertSame('https://www.autotehnicserban.ro/contact', $email->source_url);
        $this->assertTrue(WorkshopContact::query()->where('workshop_id', $workshop->id)->where('type', 'whatsapp')->exists());

        $declared = WorkshopService::query()->with('serviceType')->where('workshop_id', $workshop->id)->where('evidence_type', WorkshopEvidenceType::Website->value)->get();
        foreach (['automatic_transmission', 'transfer_case', 'differential', 'winch_installation', 'snorkel_installation', 'offroad_suspension', 'diagnostics', 'wheel_alignment'] as $key) {
            $this->assertContains($key, $declared->pluck('serviceType.key')->all());
        }
        $this->assertTrue($declared->every(fn ($service) => $service->is_authorized === null));

        $workshop->refresh();
        $this->assertSame(90, $workshop->offroad_score);
        $this->assertTrue($workshop->capabilities()->where('capability', 'offroad')->firstOrFail()->value);
    }

    public function test_without_a_search_api_discovery_uses_only_what_sources_name(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_branch')));

        $this->artisan('workshops:web:discover', ['--sync' => true, '--workshop' => $workshop->id])->assertSuccessful();

        $this->assertSame('not_found', $workshop->fresh()->website_status);
        Http::assertNothingSent();
    }
}
