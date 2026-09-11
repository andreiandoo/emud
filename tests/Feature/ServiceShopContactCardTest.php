<?php

namespace Tests\Feature;

use App\Models\ServiceShop;
use App\Models\ServiceShopLeadEvent;
use App\Models\User;
use App\Models\VehicleMake;
use Database\Seeders\DemoServiceShopSeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceShopContactCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_names_the_workshop_its_address_its_site_and_the_map(): void
    {
        $shop = $this->shop(['website' => 'https://www.vas-auto.ro/contact', 'latitude' => 44.9364, 'longitude' => 26.0133]);

        $this->get($shop->url())
            ->assertOk()
            ->assertSee('vas-auto.ro')
            ->assertSee('Str. Lungă nr. 1')
            ->assertSee('Google Maps')
            ->assertSee('Waze')
            ->assertSee('openstreetmap.org/export/embed.html', false);
    }

    public function test_waze_opens_the_workshop_and_counts_as_directions(): void
    {
        $shop = $this->shop();

        $this->get(route('storefront.service.link', ['city' => $shop->citySegment(), 'slug' => $shop->slug, 'type' => 'waze']))
            ->assertRedirect($shop->wazeUrl());

        $this->assertSame(1, ServiceShopLeadEvent::query()->where('service_shop_id', $shop->id)->where('type', 'directions')->count());
    }

    public function test_staff_can_preview_a_draft_and_nobody_else_can(): void
    {
        $shop = $this->shop(['status' => 'draft']);

        $this->get($shop->url())->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get($shop->url())->assertOk()->assertSee('Previzualizare');
    }

    public function test_the_demo_listing_has_every_part_of_a_listing_filled_in(): void
    {
        $this->seed(ServiceCatalogSeeder::class);
        VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);

        // Twice: running it again restores the same listing rather than adding a second one.
        $this->seed(DemoServiceShopSeeder::class);
        $this->seed(DemoServiceShopSeeder::class);

        $shop = ServiceShop::query()->where('slug', DemoServiceShopSeeder::SLUG)->sole();

        $this->assertSame('draft', $shop->status);
        $this->assertTrue($shop->accepts_appointments);
        $this->assertCount(7, $shop->hours);
        $this->assertGreaterThanOrEqual(8, $shop->services()->count());
        $this->assertSame(['Toyota'], $shop->makes->pluck('name')->all());
        $this->assertNotEmpty($shop->amenityLabels());
        $this->assertNotEmpty($shop->paymentLabels());
        $this->assertNotEmpty($shop->certificationLabels());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get($shop->url())->assertOk()->assertSee('Atelier Demo eMUD 4x4')->assertSee('Cere o programare');
    }

    /** Registry addresses often end in the town and county already, and the page must not add them again. */
    public function test_the_address_names_the_town_and_the_county_once(): void
    {
        $fromRegistry = $this->shop(['address' => 'Mun. Ploiești, Str. Curcubeului, Nr. 44, Jud. Prahova']);
        $typedIn = $this->shop(['slug' => 'service-lunga', 'address' => 'Str. Lungă nr. 1']);
        $lookalike = $this->shop(['slug' => 'service-deva', 'city' => 'Deva', 'county' => 'Hunedoara', 'address' => 'Str. Devasului 3']);

        $this->assertSame('Mun. Ploiești, Str. Curcubeului, Nr. 44, Jud. Prahova', $fromRegistry->fullAddress());
        $this->assertSame('Str. Lungă nr. 1, Ploiești, Prahova', $typedIn->fullAddress());
        $this->assertSame('Str. Devasului 3, Deva, Hunedoara', $lookalike->fullAddress());

        $this->get($fromRegistry->url())->assertOk()->assertDontSee('Jud. Prahova, Ploiești');
    }

    /** @param array<string, mixed> $overrides */
    private function shop(array $overrides = []): ServiceShop
    {
        return ServiceShop::create([
            'name' => 'Vas Auto Glass',
            'slug' => 'vas-auto-glass-ploiesti',
            'county' => 'Prahova',
            'city' => 'Ploiești',
            'address' => 'Str. Lungă nr. 1',
            'status' => 'published',
            ...$overrides,
        ]);
    }
}
