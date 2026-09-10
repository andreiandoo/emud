<?php

namespace Tests\Feature;

use App\Catalog\CollectionMatcher;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The rule that decides which collection page a part appears on.
 *
 * It runs during import, where nothing watches it, so every branch of it is pinned here: a part
 * silently missing from a collection looks exactly like a part the shop does not stock.
 */
class CollectionMatcherTest extends TestCase
{
    use RefreshDatabase;

    private VehicleMake $suzuki;

    private VehicleModel $jimny;

    private VehicleGeneration $third;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suzuki = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $this->jimny = VehicleModel::create(['make_id' => $this->suzuki->id, 'name' => 'Jimny', 'slug' => 'jimny', 'is_active' => true]);
        $this->third = VehicleGeneration::create(['model_id' => $this->jimny->id, 'name' => 'III', 'year_from' => 1998, 'year_to' => 2018]);
    }

    public function test_a_part_lands_in_every_level_its_fitment_touches(): void
    {
        $make = $this->collection('Suzuki', ['make_id' => $this->suzuki->id]);
        $model = $this->collection('Suzuki Jimny', ['make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id]);
        $generation = $this->collection('Suzuki Jimny III', [
            'make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id, 'generation_id' => $this->third->id,
        ]);

        $product = $this->productFittingGeneration();

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertEqualsCanonicalizing(
            [$make->id, $model->id, $generation->id],
            $product->collections()->pluck('vehicle_collections.id')->all(),
        );
    }

    /**
     * A part declared for "any Suzuki" is not evidence about the Jimny. Letting it into every
     * model collection under the make would fill each of them with parts that may not fit.
     */
    public function test_a_make_only_fitment_does_not_reach_the_model_collections(): void
    {
        $make = $this->collection('Suzuki', ['make_id' => $this->suzuki->id]);
        $this->collection('Suzuki Jimny', ['make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id]);

        $product = $this->product();
        $product->fitments()->create(['make_id' => $this->suzuki->id]);

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertSame([$make->id], $product->collections()->pluck('vehicle_collections.id')->all());
    }

    public function test_a_collection_whose_years_do_not_overlap_is_skipped(): void
    {
        $this->collection('Jimny IV', [
            'make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id, 'year_from' => 2019,
        ]);

        $product = $this->product();
        $product->fitments()->create([
            'make_id' => $this->suzuki->id,
            'model_id' => $this->jimny->id,
            'year_from' => 1998,
            'year_to' => 2018,
        ]);

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertCount(0, $product->collections()->get());
    }

    /** A collection stating no years is the common case and must match anything. */
    public function test_a_collection_without_years_matches_any_fitment(): void
    {
        $collection = $this->collection('Suzuki Jimny', ['make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id]);

        $product = $this->product();
        $product->fitments()->create(['make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id, 'year_from' => 2005, 'year_to' => 2010]);

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertSame([$collection->id], $product->collections()->pluck('vehicle_collections.id')->all());
    }

    /** An operator's decision survives the next import; the importer's own guess does not. */
    public function test_a_manual_link_is_never_removed_but_a_stale_automatic_one_is(): void
    {
        $wrong = $this->collection('Dacia Duster', ['make_id' => $this->suzuki->id, 'model_id' => $this->jimny->id]);
        $pinned = $this->collection('Editorial', []);

        $product = $this->productFittingGeneration();
        $matcher = app(CollectionMatcher::class);
        $matcher->syncForProduct($product);

        DB::table('product_vehicle_collection')->insert([
            'product_id' => $product->id,
            'vehicle_collection_id' => $pinned->id,
            'is_automatic' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The collection stops describing this car; its automatic link has to go with it.
        $wrong->update(['model_id' => null, 'make_id' => null]);
        $matcher->forget();
        $matcher->syncForProduct($product->fresh()->load('fitments'));

        $ids = $product->collections()->pluck('vehicle_collections.id')->all();

        $this->assertContains($pinned->id, $ids);
        $this->assertNotContains($wrong->id, $ids);
    }

    /** A product with nothing to fit is universal, and universal is not a collection. */
    public function test_a_product_without_fitments_joins_nothing(): void
    {
        $this->collection('Suzuki', ['make_id' => $this->suzuki->id]);

        $product = $this->product();

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertCount(0, $product->collections()->get());
    }

    public function test_an_inactive_collection_is_not_matched(): void
    {
        $this->collection('Suzuki', ['make_id' => $this->suzuki->id, 'is_active' => false]);

        $product = $this->productFittingGeneration();

        app(CollectionMatcher::class)->syncForProduct($product);

        $this->assertCount(0, $product->collections()->get());
    }

    /** @param array<string, mixed> $attributes */
    private function collection(string $name, array $attributes): VehicleCollection
    {
        return VehicleCollection::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function product(string $name = 'Kit de înălțare'): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => str($name)->slug()->value().'-'.uniqid(),
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    private function productFittingGeneration(): Product
    {
        $product = $this->product();

        $product->fitments()->create([
            'make_id' => $this->suzuki->id,
            'model_id' => $this->jimny->id,
            'generation_id' => $this->third->id,
            'year_from' => 1998,
            'year_to' => 2018,
        ]);

        return $product->load('fitments');
    }
}
