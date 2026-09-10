<?php

namespace Tests\Feature\Workshops;

use App\Workshops\Contracts\Geocoder;
use App\Workshops\Geocoding\NominatimGeocoder;
use App\Workshops\Geocoding\WorkshopGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Workshops\Concerns\BuildsWorkshops;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    use BuildsWorkshops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quietWorkshopSources();
    }

    public function test_without_a_geocoder_addresses_stay_pending_and_nothing_is_invented(): void
    {
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd', ['branch' => ['address' => ['gpsLocation' => null]]])));

        $this->artisan('workshops:geocode')->expectsOutputToContain('No geocoder is configured')->assertSuccessful();

        $this->assertNull($workshop->fresh()->latitude);
        $this->assertSame('pending', $workshop->fresh()->geocode_status);
    }

    public function test_the_public_nominatim_is_refused_unless_explicitly_allowed(): void
    {
        config(['workshops.geocoder.driver' => 'nominatim', 'workshops.geocoder.nominatim_url' => 'https://nominatim.openstreetmap.org']);
        $this->assertFalse(app(Geocoder::class)->isConfigured());

        config(['workshops.geocoder.allow_public_nominatim' => true]);
        $this->assertTrue(app(NominatimGeocoder::class)->isConfigured());
    }

    public function test_a_street_level_answer_in_the_right_county_places_the_workshop(): void
    {
        config(['workshops.geocoder.driver' => 'nominatim', 'workshops.geocoder.nominatim_url' => 'https://geo.internal.example']);
        Http::fake(['geo.internal.example/*' => Http::response([['lat' => '45.6624', 'lon' => '25.5712', 'place_rank' => 30]])]);
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd', ['branch' => ['address' => ['gpsLocation' => null]]])));

        $counts = app(WorkshopGeocoder::class)->run(10);

        $this->assertSame(1, $counts['located']);
        $workshop->refresh();
        $this->assertSame(45.6624, $workshop->latitude);
        $this->assertSame('geocoder:nominatim', $workshop->coordinates_source);
        $this->assertSame('located', $workshop->geocode_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'countrycodes=ro'));
    }

    public function test_an_answer_in_another_county_is_not_a_location(): void
    {
        config(['workshops.geocoder.driver' => 'nominatim', 'workshops.geocoder.nominatim_url' => 'https://geo.internal.example']);
        Http::fake(['geo.internal.example/*' => Http::response([['lat' => '44.43', 'lon' => '26.10', 'place_rank' => 30]])]);
        $workshop = $this->workshopFor($this->ingestRar('SERVICE', $this->rarPayload('service_awd', ['branch' => ['address' => ['gpsLocation' => null]]])));

        $counts = app(WorkshopGeocoder::class)->run(10);

        $this->assertSame(1, $counts['failed']);
        $this->assertNull($workshop->fresh()->latitude);
        $this->assertSame('failed', $workshop->fresh()->geocode_status);
    }
}
