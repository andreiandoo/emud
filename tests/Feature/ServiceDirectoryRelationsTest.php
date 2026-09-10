<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceShop;
use App\Models\User;
use App\Models\VehicleMake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every relation in the directory, written and read back.
 *
 * Eloquent guesses table and key names from class names, and a wrong guess is silent until the
 * first query touches the relation — which is how a mis-derived pivot name took out three pages
 * at once rather than failing anywhere near where it was written. This exercises each one so
 * that guess is checked by a test instead of by a visitor.
 */
class ServiceDirectoryRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_workshop_relation_round_trips(): void
    {
        $shop = ServiceShop::create([
            'name' => 'Atelierul Test',
            'slug' => 'atelierul-test',
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'status' => 'published',
        ]);

        $category = ServiceCategory::create(['name' => 'Revizie', 'slug' => 'revizie']);
        $service = Service::create([
            'service_category_id' => $category->id,
            'name' => 'Schimb ulei',
            'slug' => 'schimb-ulei',
            'is_active' => true,
        ]);
        $make = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);

        $shop->services()->attach($service->id, ['price_from' => 150, 'currency' => 'RON', 'duration_minutes' => 60]);
        $shop->makes()->attach($make->id);
        $shop->hours()->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        $shop->media()->create(['disk' => 'public', 'path' => 'service-shops/a.jpg', 'position' => 0]);
        $shop->leadEvents()->create(['type' => 'phone_reveal']);
        $shop->appointments()->create([
            'token' => (string) Str::uuid(),
            'user_id' => User::factory()->create()->id,
            'customer_name' => 'Ion',
            'customer_phone' => '0722000111',
        ]);

        $fresh = ServiceShop::query()
            ->with(['services.serviceCategory', 'makes', 'hours', 'media', 'appointments.user', 'leadEvents'])
            ->withCount(['services', 'appointments'])
            ->findOrFail($shop->id);

        $this->assertSame(1, $fresh->services_count);
        $this->assertSame(1, $fresh->appointments_count);
        $this->assertSame('Schimb ulei', $fresh->services->first()->name);
        $this->assertSame('Revizie', $fresh->services->first()->serviceCategory->name);
        // Compared as a number: Postgres hands back 150.00 and SQLite 150, and the suite runs
        // on both.
        $this->assertEquals(150, $fresh->services->first()->pivot->price_from);
        $this->assertSame('Suzuki', $fresh->makes->first()->name);
        $this->assertSame(1, $fresh->hours->first()->weekday);
        $this->assertSame('service-shops/a.jpg', $fresh->media->first()->path);
        $this->assertSame('Ion', $fresh->appointments->first()->customer_name);
        $this->assertCount(1, $fresh->leadEvents);
    }

    public function test_the_relation_reads_the_same_way_from_the_service_side(): void
    {
        $shop = ServiceShop::create([
            'name' => 'Atelierul Test', 'slug' => 'atelierul-test',
            'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'status' => 'published',
        ]);

        $category = ServiceCategory::create(['name' => 'Revizie', 'slug' => 'revizie']);
        $service = Service::create([
            'service_category_id' => $category->id,
            'name' => 'Schimb ulei', 'slug' => 'schimb-ulei', 'is_active' => true,
        ]);

        $service->shops()->attach($shop->id, ['price_from' => 150, 'currency' => 'RON']);

        $this->assertSame(1, Service::query()->withCount('shops')->findOrFail($service->id)->shops_count);
        $this->assertSame('Atelierul Test', $service->fresh()->shops->first()->name);
        $this->assertSame(1, ServiceCategory::query()->withCount('services')->findOrFail($category->id)->services_count);
    }

    /**
     * The three pages the mis-derived pivot took down, asked for directly. A workshop that is
     * merely listed is enough — the fault was in loading the relation, not in its contents.
     */
    public function test_the_pages_that_load_the_relation_still_render(): void
    {
        $shop = ServiceShop::create([
            'name' => 'Atelierul Test', 'slug' => 'atelierul-test',
            'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'status' => 'published',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('admin.service-catalog'))->assertOk();
        $this->get(route('admin.service-shops.index'))->assertOk()->assertSee('Atelierul Test');
        $this->get(route('storefront.services'))->assertOk()->assertSee('Atelierul Test');
        $this->get($shop->url())->assertOk();
    }
}
