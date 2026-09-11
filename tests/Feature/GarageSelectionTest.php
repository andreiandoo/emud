<?php

namespace Tests\Feature;

use App\Livewire\Storefront\SearchResults;
use App\Livewire\Storefront\VehicleSelector;
use App\Models\Brand;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A customer with more than one car ticks the ones they are shopping for in the header, and
 * search and listings show what fits any of them.
 */
class GarageSelectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CustomerVehicle $jimny;

    private CustomerVehicle $hilux;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->jimny = $this->garageVehicle('Suzuki', 'Jimny', primary: true);
        $this->hilux = $this->garageVehicle('Toyota', 'Hilux', primary: false);
        $this->actingAs($this->user);
    }

    public function test_several_garage_cars_can_be_ticked_together(): void
    {
        Livewire::test(VehicleSelector::class)
            ->call('toggleFromGarage', $this->hilux->id)
            ->assertDispatched('vehicle-changed');

        $selection = app(VehicleContext::class)->selection();

        $this->assertCount(2, $selection);
        $this->assertSame('Suzuki Jimny și Toyota Hilux', $selection->label());
        // The primary car stays first, whichever box was ticked first.
        $this->assertSame($this->jimny->id, app(VehicleContext::class)->current()?->customerVehicleId);
    }

    public function test_the_last_ticked_car_stays_ticked(): void
    {
        Livewire::test(VehicleSelector::class)->call('toggleFromGarage', $this->jimny->id);

        $this->assertSame([$this->jimny->id], app(VehicleContext::class)->selection()->garageIds());
    }

    public function test_unticking_one_of_two_keeps_the_other(): void
    {
        Livewire::test(VehicleSelector::class)
            ->call('toggleFromGarage', $this->hilux->id)
            ->call('toggleFromGarage', $this->jimny->id);

        $this->assertSame([$this->hilux->id], app(VehicleContext::class)->selection()->garageIds());
    }

    public function test_every_car_can_be_ticked_at_once(): void
    {
        Livewire::test(VehicleSelector::class)->call('chooseAllFromGarage');

        $this->assertSame([$this->jimny->id, $this->hilux->id], app(VehicleContext::class)->selection()->garageIds());
    }

    /** Cars leave the garage from the garage page, so the header has nothing that drops one. */
    public function test_the_header_offers_no_button_to_drop_the_car(): void
    {
        Livewire::test(VehicleSelector::class)
            ->assertSee('Doar piese pentru mașinile mele')
            ->assertSee('Bifează toate')
            ->assertDontSee('Renunță');
    }

    public function test_search_covers_every_ticked_car(): void
    {
        $this->part('Arc Jimny', $this->jimny);
        $this->part('Arc Hilux', $this->hilux);

        Livewire::test(SearchResults::class)
            ->set('query', 'Arc')
            ->assertSee('Arc Jimny')
            ->assertDontSee('Arc Hilux');

        Livewire::test(VehicleSelector::class)->call('toggleFromGarage', $this->hilux->id);

        Livewire::test(SearchResults::class)
            ->set('query', 'Arc')
            ->assertSee('Arc Jimny')
            ->assertSee('Arc Hilux');
    }

    private function part(string $name, CustomerVehicle $vehicle): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'published_at' => now(),
            'brand_id' => Brand::create(['name' => 'Brand '.Str::random(4), 'slug' => Str::random(8)])->id,
        ]);

        ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(8), 'retail_price' => 499, 'is_active' => true]);
        ProductFitment::create(['product_id' => $product->id, 'make_id' => $vehicle->make_id, 'model_id' => $vehicle->model_id]);

        return $product;
    }

    private function garageVehicle(string $make, string $model, bool $primary): CustomerVehicle
    {
        $makeRow = VehicleMake::create(['name' => $make, 'slug' => Str::slug($make), 'is_active' => true]);
        $modelRow = VehicleModel::create(['make_id' => $makeRow->id, 'name' => $model, 'slug' => Str::slug($model)]);

        return CustomerVehicle::create([
            'user_id' => $this->user->id,
            'make_id' => $makeRow->id,
            'model_id' => $modelRow->id,
            'year' => 2018,
            'is_primary' => $primary,
        ]);
    }
}
