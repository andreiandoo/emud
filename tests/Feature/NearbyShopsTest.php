<?php

namespace Tests\Feature;

use App\Directory\Localities;
use App\Directory\NearbyShops;
use App\Models\ServiceShop;
use App\Models\User;
use App\Storefront\AddressBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NearbyShopsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshops_in_the_customers_town_come_before_the_rest_of_the_county(): void
    {
        $user = $this->customerIn('Ploiești', 'Prahova');
        $this->shop('Service Câmpina', 'Câmpina');
        $this->shop('Service Ploiești', 'Ploiești');
        $this->shop('Service Cluj', 'Cluj-Napoca', 'Cluj');

        $this->assertSame(
            ['Service Ploiești', 'Service Câmpina'],
            app(NearbyShops::class)->forUser($user)->pluck('name')->all(),
        );
    }

    public function test_the_overview_recommends_workshops_near_the_customer(): void
    {
        $this->actingAs($this->customerIn('Ploiești', 'Prahova'));
        $this->shop('Service Ploiești', 'Ploiești');

        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Service-uri lângă tine')->assertSee('Service Ploiești');
    }

    public function test_a_customer_with_no_town_is_asked_for_one(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('customer.dashboard'))->assertOk()->assertSee('Completează orașul');
    }

    public function test_the_town_list_suggests_the_towns_of_a_county_with_its_seat_first(): void
    {
        $this->shop('Service Câmpina', 'Câmpina');

        $towns = Localities::forCounty('Prahova');

        $this->assertSame('Ploiești', $towns[0]);
        $this->assertContains('Câmpina', $towns);
        $this->assertContains('București', Localities::counties());
        $this->assertCount(42, Localities::counties());
    }

    private function customerIn(string $city, string $county): User
    {
        $user = User::factory()->create();

        app(AddressBook::class)->saveShipping($user, [
            'first_name' => 'Andrei',
            'last_name' => 'Popescu',
            'line_1' => 'Str. Exemplu 1',
            'city' => $city,
            'county' => $county,
        ]);

        return $user;
    }

    private function shop(string $name, string $city, string $county = 'Prahova'): ServiceShop
    {
        return ServiceShop::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'county' => $county,
            'city' => $city,
            'status' => 'published',
        ]);
    }
}
