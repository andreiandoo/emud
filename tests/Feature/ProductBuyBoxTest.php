<?php

namespace Tests\Feature;

use App\Enums\StockStatus;
use App\Livewire\Storefront\CategoryPage;
use App\Livewire\Storefront\ProductPage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductVariant;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceShop;
use App\Models\ShippingMethod;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\AddressBook;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product page's buy box: stock and delivery per country, the fit in one line, the
 * workshops that fit the part, and what a listing card says about the same part.
 */
class ProductBuyBoxTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Amortizoare',
            'slug' => 'amortizoare',
            'full_path' => 'amortizoare',
            'depth' => 0,
            'is_active' => true,
            'is_visible_in_menu' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Set amortizoare off-road',
            'slug' => 'set-amortizoare-off-road',
            'status' => 'active',
            'published_at' => now(),
            'brand_id' => Brand::create(['name' => 'Ironman', 'slug' => 'ironman'])->id,
        ]);
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => 'IM-1', 'retail_price' => 899, 'is_active' => true]);
        $this->product->categories()->attach($this->category);
    }

    /** RO ships in a day plus one to two in transit; PL in one to two plus two to four. */
    public function test_stock_is_listed_per_country_with_the_delivery_window(): void
    {
        $this->offer('RO', dispatch: [1, 1]);
        $this->offer('PL', dispatch: [1, 2]);
        ShippingMethod::query()->create(['code' => 'curier', 'name' => 'Curier', 'base_price' => 20, 'free_over' => 500, 'is_active' => true]);

        $this->get(route('storefront.product', $this->product))
            ->assertOk()
            ->assertSee('În stoc')
            ->assertSee('Stoc disponibil la 2 furnizori')
            ->assertSee('livrabil în 2–3 zile lucrătoare')
            ->assertSee('livrabil în 3–6 zile lucrătoare')
            ->assertSee('Livrare gratuită');
    }

    public function test_stock_from_a_feed_past_its_window_is_shown_as_to_be_confirmed(): void
    {
        $this->offer('RO', dispatch: [1, 1], staleAfter: now()->subDay());

        $this->get(route('storefront.product', $this->product))
            ->assertOk()
            ->assertSee('Stoc la furnizor')
            ->assertSee('confirmăm stocul la comandă');
    }

    public function test_the_fit_is_one_line_naming_the_car(): void
    {
        $this->selectCarTheProductDoesNotFit();

        $this->get(route('storefront.product', $this->product))
            ->assertOk()
            ->assertSee('Nu se potrivește')
            ->assertSee('cu Toyota Hilux')
            ->assertDontSee('Pe mașina ta');
    }

    public function test_the_workshops_that_fit_it_open_over_the_page_near_the_customer(): void
    {
        $user = User::factory()->create();
        app(AddressBook::class)->saveShipping($user, ['first_name' => 'Ana', 'last_name' => 'Pop', 'line_1' => 'Str. Exemplu 1', 'city' => 'Ploiești', 'county' => 'Prahova']);

        $service = Service::create([
            'service_category_id' => ServiceCategory::create(['name' => 'Suspensie', 'slug' => 'suspensie', 'icon' => 'suspension'])->id,
            'name' => 'Schimb amortizoare',
            'slug' => 'schimb-amortizoare',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
        $this->shop('Service Ploiești', 'Ploiești', 'Prahova')->services()->attach($service->id, ['currency' => 'RON']);
        $this->shop('Service Cluj', 'Cluj-Napoca', 'Cluj')->services()->attach($service->id, ['currency' => 'RON']);

        $this->actingAs($user);

        Livewire::test(ProductPage::class, ['product' => $this->product])
            ->assertSee('Schimb amortizoare · lângă Ploiești')
            ->assertDontSee('Service Ploiești')
            ->call('findShops')
            ->assertSee('Service Ploiești')
            ->assertDontSee('Service Cluj')
            ->assertSee(e(route('storefront.services', ['county' => 'Prahova', 'service' => 'schimb-amortizoare'])), false);
    }

    public function test_the_favourite_button_says_when_the_part_is_saved(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ProductPage::class, ['product' => $this->product])
            ->assertSee('Salvează la favorite')
            ->call('toggleWishlist')
            ->assertSee('Scoate din favorite');
    }

    /** A card says when the part ships, and never "does not fit" on a catalogue shown whole. */
    public function test_a_card_says_when_the_part_ships_and_not_that_it_does_not_fit(): void
    {
        $this->offer('RO', dispatch: [1, 1]);
        $this->selectCarTheProductDoesNotFit();
        app(VehicleContext::class)->setFiltersParts(false);

        Livewire::test(CategoryPage::class, ['category' => $this->category])
            ->assertSee('Set amortizoare off-road')
            ->assertSee('În stoc · livrare în 2–3 zile')
            ->assertDontSee('Nu se potrivește');
    }

    private function selectCarTheProductDoesNotFit(): void
    {
        $toyota = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $hilux = VehicleModel::create(['make_id' => $toyota->id, 'name' => 'Hilux', 'slug' => 'hilux']);
        $dacia = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia']);

        ProductFitment::create(['product_id' => $this->product->id, 'make_id' => $dacia->id]);
        app(VehicleContext::class)->select(new SelectedVehicle($toyota->id, 'Toyota', $hilux->id, 'Hilux'));
    }

    /** @param  array{0: int, 1: int}  $dispatch */
    private function offer(string $country, array $dispatch, mixed $staleAfter = null): SupplierOffer
    {
        $supplier = Supplier::query()->create([
            'name' => 'Furnizor '.$country,
            'code' => 'furnizor-'.Str::lower($country),
            'protocol' => 'csv',
            'country_code' => $country,
            'is_active' => true,
        ]);

        $row = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $this->product->id,
            'external_id' => 'AM-'.$country,
            'name' => $this->product->name,
        ]);

        return SupplierOffer::query()->create([
            'supplier_product_id' => $row->id,
            'currency' => 'RON',
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => 6,
            'dispatch_days_min' => $dispatch[0],
            'dispatch_days_max' => $dispatch[1],
            'is_active' => true,
            'stale_after' => $staleAfter,
        ]);
    }

    private function shop(string $name, string $city, string $county): ServiceShop
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
