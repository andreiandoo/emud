<?php

namespace Tests\Feature;

use App\Enums\ServiceLeadEventType;
use App\Livewire\Storefront\ServiceShopPage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceShopPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_hours_services_and_facilities(): void
    {
        $shop = $this->shop();
        $shop->hours()->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        $shop->services()->attach($this->service()->id, ['price_from' => 150, 'currency' => 'RON', 'duration_minutes' => 60]);
        $shop->update(['amenities' => ['waiting_room'], 'payment_methods' => ['card'], 'certifications' => ['rar_itp']]);

        $this->get($shop->url())
            ->assertOk()
            ->assertSee('Luni')
            ->assertSee('Schimb ulei')
            ->assertSee('Sală de așteptare')
            ->assertSee('Card bancar')
            ->assertSee('Stație ITP autorizată RAR');
    }

    /**
     * A retired option must not leave an unlabelled chip on a public page, so anything stored
     * that the vocabulary no longer offers is dropped on read.
     */
    public function test_an_unknown_facility_key_is_not_rendered(): void
    {
        $shop = $this->shop();
        $shop->update(['amenities' => ['waiting_room', 'ceva_ce_nu_exista']]);

        $this->get($shop->url())->assertOk()->assertDontSee('ceva_ce_nu_exista');
    }

    public function test_the_wrong_city_segment_redirects_to_the_canonical_address(): void
    {
        $shop = $this->shop();

        $this->get('/service-auto/alt-oras/'.$shop->slug)->assertRedirect($shop->url());
    }

    /**
     * Workshops lived at a one-segment address before the city was part of the URL. Those links
     * are in the wild, so they move rather than break.
     */
    public function test_an_old_one_segment_link_redirects_permanently(): void
    {
        $shop = $this->shop();

        $this->get('/service-auto/'.$shop->slug)->assertStatus(301)->assertRedirect($shop->url());
    }

    public function test_the_city_page_lists_the_town(): void
    {
        $shop = $this->shop();

        $this->get(route('storefront.services.city', $shop->citySegment()))
            ->assertOk()
            ->assertSee('Cluj-Napoca')
            ->assertSee($shop->name);
    }

    public function test_an_unknown_city_is_a_not_found(): void
    {
        $this->get('/service-auto/oras-inexistent')->assertNotFound();
    }

    /**
     * Structured data must describe what the page shows. No reviews are published, so no rating
     * is claimed.
     */
    public function test_the_page_carries_local_business_structured_data_without_a_rating(): void
    {
        $shop = $this->shop();
        $shop->hours()->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);

        $content = $this->get($shop->url())->getContent();

        $this->assertStringContainsString('"@type":"AutoRepair"', $content);
        $this->assertStringContainsString('"addressCountry":"RO"', $content);
        $this->assertStringContainsString('"dayOfWeek":"Monday"', $content);
        $this->assertStringNotContainsString('aggregateRating', $content);
    }

    public function test_revealing_the_phone_number_is_counted_once_per_visit(): void
    {
        $shop = $this->shop();

        Livewire::test(ServiceShopPage::class, ['city' => $shop->citySegment(), 'slug' => $shop->slug])
            ->call('revealPhone')
            ->assertSet('phoneVisible', true)
            ->assertSee('0264 123 456')
            ->call('revealPhone');

        $this->assertSame(1, ServiceShopLeadEvent::query()->where('type', ServiceLeadEventType::PhoneReveal)->count());
    }

    public function test_the_outgoing_link_is_counted_and_redirects_to_the_workshop(): void
    {
        $shop = $this->shop();
        $shop->update(['website' => 'https://atelier.example']);

        $this->get(route('storefront.service.link', [
            'city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'website',
        ]))->assertRedirect('https://atelier.example');

        $this->assertSame(1, ServiceShopLeadEvent::query()->where('type', ServiceLeadEventType::WebsiteClick)->count());
    }

    public function test_an_outgoing_link_a_workshop_did_not_supply_is_a_not_found(): void
    {
        $shop = $this->shop();

        $this->get(route('storefront.service.link', [
            'city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'website',
        ]))->assertNotFound();

        $this->assertSame(0, ServiceShopLeadEvent::query()->count());
    }

    public function test_a_service_page_lists_the_workshops_that_offer_it(): void
    {
        $shop = $this->shop();
        $service = $this->service();
        $shop->services()->attach($service->id, ['price_from' => 150, 'currency' => 'RON']);

        $this->get(route('storefront.service-type', $service->slug))
            ->assertOk()
            ->assertSee('Schimb ulei')
            ->assertSee($shop->name);
    }

    public function test_the_sitemap_lists_workshops_cities_and_services(): void
    {
        $shop = $this->shop();
        $service = $this->service();

        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString($shop->url(), $content);
        $this->assertStringContainsString(route('storefront.services.city', $shop->citySegment()), $content);
        $this->assertStringContainsString(route('storefront.service-type', $service->slug), $content);
    }

    private function shop(): ServiceShop
    {
        return ServiceShop::create([
            'name' => 'Atelierul Test',
            'slug' => 'atelierul-test',
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'address' => 'Str. Fabricii 12',
            'phone' => '0264 123 456',
            'status' => 'published',
        ]);
    }

    private function service(): Service
    {
        $category = ServiceCategory::create(['name' => 'Revizie', 'slug' => 'revizie']);

        return Service::create([
            'service_category_id' => $category->id,
            'name' => 'Schimb ulei',
            'slug' => 'schimb-ulei',
            'is_active' => true,
        ]);
    }
}
