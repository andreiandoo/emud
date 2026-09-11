<?php

namespace Tests\Feature;

use App\Livewire\Storefront\CategoryPage;
use App\Livewire\Storefront\SearchResults;
use App\Livewire\Storefront\VehicleFinder;
use App\Livewire\Storefront\VehicleSelector;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductFitment;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\Compatibility\CompatibilityVerdict;
use App\Storefront\Compatibility\FitmentMatcher;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    private VehicleMake $make;

    private VehicleModel $model;

    private VehicleGeneration $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = VehicleMake::create(['name' => 'Toyota', 'slug' => 'toyota']);
        $this->model = VehicleModel::create(['make_id' => $this->make->id, 'name' => 'Hilux', 'slug' => 'hilux']);
        $this->generation = VehicleGeneration::create(['model_id' => $this->model->id, 'name' => 'AN120', 'year_from' => 2015]);
    }

    public function test_a_universal_product_fits_every_vehicle(): void
    {
        $product = $this->product(universal: true);

        $this->assertSame(
            CompatibilityVerdict::Confirmed,
            app(FitmentMatcher::class)->verdictFor($product, $this->vehicle())
        );
    }

    public function test_a_product_without_fitment_data_stays_unknown(): void
    {
        $this->assertSame(
            CompatibilityVerdict::Unknown,
            app(FitmentMatcher::class)->verdictFor($this->product(), $this->vehicle())
        );
    }

    public function test_a_matching_fitment_confirms_the_product(): void
    {
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'model_id' => $this->model->id]);

        $this->assertSame(
            CompatibilityVerdict::Confirmed,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle())
        );
    }

    public function test_a_fitment_requiring_modification_is_conditional(): void
    {
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'requires_modification' => true]);

        $this->assertSame(
            CompatibilityVerdict::Conditional,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle())
        );
    }

    public function test_fitment_data_for_other_models_only_marks_the_product_incompatible(): void
    {
        $otherModel = VehicleModel::create(['make_id' => $this->make->id, 'name' => 'Land Cruiser', 'slug' => 'land-cruiser']);
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'model_id' => $otherModel->id]);

        $this->assertSame(
            CompatibilityVerdict::Incompatible,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle())
        );
    }

    /**
     * Answering yes or no here would be a guess, and a wrong yes is a return and a refund.
     */
    public function test_an_unknown_generation_asks_for_detail_instead_of_guessing(): void
    {
        $product = $this->product();
        $this->fitment($product, [
            'make_id' => $this->make->id,
            'model_id' => $this->model->id,
            'generation_id' => $this->generation->id,
        ]);

        $this->assertSame(
            CompatibilityVerdict::RequiresVehicleDetail,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle(withGeneration: false))
        );
    }

    public function test_a_known_generation_that_differs_is_incompatible(): void
    {
        $otherGeneration = VehicleGeneration::create(['model_id' => $this->model->id, 'name' => 'AN80', 'year_from' => 2005]);
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'model_id' => $this->model->id, 'generation_id' => $otherGeneration->id]);

        $this->assertSame(
            CompatibilityVerdict::Incompatible,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle())
        );
    }

    public function test_a_year_outside_the_fitment_range_is_incompatible(): void
    {
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'year_from' => 2005, 'year_to' => 2010]);

        $this->assertSame(
            CompatibilityVerdict::Incompatible,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle(year: 2020))
        );
    }

    public function test_a_year_range_cannot_be_judged_without_the_year(): void
    {
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'year_from' => 2005, 'year_to' => 2010]);

        $this->assertSame(
            CompatibilityVerdict::RequiresVehicleDetail,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle(year: null))
        );
    }

    /**
     * One row excluding the vehicle says nothing about the others, so the most favourable
     * answer any row supports wins.
     */
    public function test_one_matching_row_outweighs_a_non_matching_one(): void
    {
        $otherModel = VehicleModel::create(['make_id' => $this->make->id, 'name' => 'Land Cruiser', 'slug' => 'land-cruiser']);
        $product = $this->product();
        $this->fitment($product, ['make_id' => $this->make->id, 'model_id' => $otherModel->id]);
        $this->fitment($product, ['make_id' => $this->make->id, 'model_id' => $this->model->id]);

        $this->assertSame(
            CompatibilityVerdict::Confirmed,
            app(FitmentMatcher::class)->verdictFor($product->fresh(), $this->vehicle())
        );
    }

    public function test_the_category_listing_hides_products_that_do_not_fit(): void
    {
        $category = $this->category();
        $fitting = $this->product(name: 'Bară fitting');
        $notFitting = $this->product(name: 'Bară straina');
        $otherMake = VehicleMake::create(['name' => 'Jeep', 'slug' => 'jeep']);

        $fitting->categories()->attach($category);
        $notFitting->categories()->attach($category);
        $this->fitment($fitting, ['make_id' => $this->make->id]);
        $this->fitment($notFitting, ['make_id' => $otherMake->id]);

        app(VehicleContext::class)->select($this->vehicle());

        Livewire::test(CategoryPage::class, ['category' => $category])
            ->assertSee('Bară fitting')
            ->assertDontSee('Bară straina');
    }

    public function test_unticking_the_filter_shows_the_whole_category(): void
    {
        $category = $this->category();
        $otherMake = VehicleMake::create(['name' => 'Jeep', 'slug' => 'jeep']);
        $notFitting = $this->product(name: 'Bară straina');
        $notFitting->categories()->attach($category);
        $this->fitment($notFitting, ['make_id' => $otherMake->id]);

        app(VehicleContext::class)->select($this->vehicle());

        Livewire::test(CategoryPage::class, ['category' => $category])
            ->assertDontSee('Bară straina')
            ->set('onlyForMyVehicle', false)
            ->assertSee('Bară straina');
    }

    public function test_a_category_lists_products_filed_under_its_subcategories(): void
    {
        $parent = $this->category('Suspensie', 'suspensie');
        $child = $this->category('Arcuri', 'suspensie/arcuri', $parent);
        $product = $this->product(name: 'Arc spate');
        $product->categories()->attach($child);

        Livewire::test(CategoryPage::class, ['category' => $parent])->assertSee('Arc spate');
    }

    public function test_search_matches_a_part_number_exactly(): void
    {
        $this->product(name: 'Filtru ulei', sku: 'OC-90');

        Livewire::test(SearchResults::class)
            ->set('query', 'OC-90')
            ->assertSee('Filtru ulei');
    }

    public function test_search_finds_nothing_before_a_term_is_typed(): void
    {
        $this->product(name: 'Filtru ulei');

        Livewire::test(SearchResults::class)->assertDontSee('Filtru ulei');
    }

    public function test_an_unpublished_product_is_not_reachable(): void
    {
        $product = Product::create([
            'name' => 'Ciornă',
            'slug' => 'ciorna',
            'status' => 'draft',
        ]);

        $this->get(route('storefront.product', $product))->assertNotFound();
    }

    public function test_a_category_can_be_narrowed_to_one_brand(): void
    {
        $category = $this->category();
        $kept = $this->product(name: 'Arc Old Man Emu');
        $dropped = $this->product(name: 'Arc Ironman');
        $kept->categories()->attach($category);
        $dropped->categories()->attach($category);

        Livewire::test(CategoryPage::class, ['category' => $category])
            ->set('brands', [$kept->brand->slug])
            ->assertSee('Arc Old Man Emu')
            ->assertDontSee('Arc Ironman');
    }

    public function test_a_category_can_be_narrowed_to_one_of_its_subcategories(): void
    {
        $parent = $this->category('Suspensie', 'suspensie');
        $springs = $this->category('Arcuri', 'suspensie/arcuri', $parent);
        $shocks = $this->category('Amortizoare', 'suspensie/amortizoare', $parent);
        $this->product(name: 'Arc spate')->categories()->attach($springs);
        $this->product(name: 'Amortizor fata')->categories()->attach($shocks);

        Livewire::test(CategoryPage::class, ['category' => $parent])
            ->set('subcategories', ['arcuri'])
            ->assertSee('Arc spate')
            ->assertDontSee('Amortizor fata');
    }

    public function test_a_specification_filter_keeps_only_the_parts_that_have_it(): void
    {
        $category = $this->category();
        $lift = Attribute::create(['name' => 'Înălțare', 'code' => 'inaltare', 'type' => 'number', 'unit' => 'mm']);
        $lift->categories()->attach($category, ['is_filterable' => true]);

        foreach (['Kit înălțare 50' => 50, 'Kit înălțare 70' => 70] as $name => $height) {
            $product = $this->product(name: $name);
            $product->categories()->attach($category);
            ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $lift->id, 'value_number' => $height]);
        }

        Livewire::test(CategoryPage::class, ['category' => $category])
            ->assertSee('Kit înălțare 70')
            ->call('toggleSpec', 'inaltare', 'n50')
            ->assertSee('Kit înălțare 50')
            ->assertDontSee('Kit înălțare 70');
    }

    /** A value typed into the URL by hand is ignored, not turned into an error page. */
    public function test_a_malformed_specification_filter_is_ignored(): void
    {
        $category = $this->category();
        $this->product(name: 'Bară față')->categories()->attach($category);

        Livewire::withQueryParams(['f' => ['inaltare' => 'nu-e-lista', 5 => ['x']]])
            ->test(CategoryPage::class, ['category' => $category])
            ->assertSee('Bară față');
    }

    public function test_on_a_category_the_finder_offers_its_subcategories(): void
    {
        $parent = $this->category('Suspensie', 'suspensie');
        $this->category('Arcuri', 'suspensie/arcuri', $parent);

        Livewire::test(VehicleFinder::class, ['category' => $parent])
            ->assertSee('Alege mașina ta')
            ->assertSee('Alege subcategoria')
            ->assertSee('Arcuri');
    }

    /** The code on the box usually comes with a supplier's feed, not typed onto the product. */
    public function test_search_finds_a_part_by_the_ean_on_its_supplier_record(): void
    {
        $product = $this->product(name: 'Filtru ulei');
        $supplier = Supplier::query()->create(['name' => 'Furnizor', 'code' => 'furnizor', 'protocol' => 'csv']);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'external_id' => 'F-1',
            'ean' => '5901234123457',
            'name' => 'Oil filter',
        ]);

        Livewire::test(SearchResults::class)
            ->set('query', '5901234123457')
            ->assertSee('Filtru ulei');
    }

    /** Unticked once, the filter stays off on the next page instead of having to be unticked again. */
    public function test_unticking_the_filter_on_a_category_carries_over_to_search(): void
    {
        $category = $this->category();
        $otherMake = VehicleMake::create(['name' => 'Jeep', 'slug' => 'jeep']);
        $notFitting = $this->product(name: 'Bară straina');
        $notFitting->categories()->attach($category);
        $this->fitment($notFitting, ['make_id' => $otherMake->id]);

        app(VehicleContext::class)->select($this->vehicle());

        Livewire::test(CategoryPage::class, ['category' => $category])
            ->set('onlyForMyVehicle', false)
            ->assertDispatched('vehicle-changed');

        Livewire::test(SearchResults::class)
            ->assertSet('onlyForMyVehicle', false)
            ->set('query', 'Bară')
            ->assertSee('Bară straina');
    }

    public function test_a_signed_in_customer_keeps_the_choice_on_their_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(SearchResults::class)->set('onlyForMyVehicle', false);

        $this->assertFalse($user->fresh()->filters_parts_by_vehicle);

        // A new visit starts with an empty session, and the account still remembers.
        session()->forget('storefront.vehicle-filter');
        $this->assertFalse(app(VehicleContext::class)->filtersParts());
    }

    public function test_the_header_switch_turns_the_filter_off_and_back_on(): void
    {
        Livewire::test(VehicleSelector::class)
            ->call('toggleFilter')
            ->assertDispatched('vehicle-changed');

        $this->assertFalse(app(VehicleContext::class)->filtersParts());

        Livewire::test(VehicleSelector::class)->call('toggleFilter');

        $this->assertTrue(app(VehicleContext::class)->filtersParts());
    }

    private function vehicle(bool $withGeneration = true, ?int $year = 2020): SelectedVehicle
    {
        return new SelectedVehicle(
            makeId: $this->make->id,
            makeName: 'Toyota',
            modelId: $this->model->id,
            modelName: 'Hilux',
            generationId: $withGeneration ? $this->generation->id : null,
            generationName: $withGeneration ? 'AN120' : null,
            year: $year,
        );
    }

    private function product(string $name = 'Produs', bool $universal = false, ?string $sku = null): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'sku' => $sku,
            'status' => 'active',
            'published_at' => now(),
            'is_universal' => $universal,
            'brand_id' => Brand::create(['name' => 'Brand '.Str::random(4), 'slug' => Str::random(8)])->id,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V-'.Str::random(8),
            'retail_price' => 199.99,
            'is_active' => true,
        ]);

        return $product;
    }

    /** @param array<string, mixed> $attributes */
    private function fitment(Product $product, array $attributes): ProductFitment
    {
        return ProductFitment::create([...$attributes, 'product_id' => $product->id]);
    }

    private function category(string $name = 'Off-road', string $path = 'off-road', ?Category $parent = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::afterLast($path, '/'),
            'full_path' => $path,
            'parent_id' => $parent?->id,
            'depth' => substr_count($path, '/'),
            'is_active' => true,
            'is_visible_in_menu' => true,
        ]);
    }
}
