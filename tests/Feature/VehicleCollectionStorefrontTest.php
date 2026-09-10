<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\VehicleCollection;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\CollectionShowcase;
use App\Storefront\Garage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCollectionStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_collection_page_lists_what_is_attached_to_it(): void
    {
        $collection = $this->collection();
        $inside = $this->product('Kit de înălțare Jimny');
        $outside = $this->product('Plăcuțe de frână Duster');

        $collection->products()->attach($inside->id, ['is_automatic' => true]);

        $this->get($collection->url())
            ->assertOk()
            ->assertSee('Suzuki Jimny')
            ->assertSee('Kit de înălțare Jimny')
            ->assertDontSee('Plăcuțe de frână Duster');
    }

    public function test_a_hidden_collection_is_not_reachable(): void
    {
        $collection = $this->collection();
        $collection->update(['is_active' => false]);

        $this->get($collection->url())->assertNotFound();
    }

    public function test_the_page_states_its_own_metadata_and_breadcrumbs(): void
    {
        $collection = $this->collection();
        $collection->update([
            'seo_title' => 'Tot pentru Jimny',
            'seo_description' => 'Piese și accesorii pentru Suzuki Jimny.',
            'robots_index' => false,
        ]);

        $this->get($collection->url())
            ->assertOk()
            ->assertSee('Tot pentru Jimny')
            ->assertSee('Piese și accesorii pentru Suzuki Jimny.')
            ->assertSee('noindex,follow')
            ->assertSee('BreadcrumbList');
    }

    /**
     * The carousel falls back to the collections with products behind them, so a shop that has
     * not curated anything still shows something rather than an empty strip.
     */
    public function test_the_carousel_falls_back_to_collections_that_have_products(): void
    {
        $withProducts = $this->collection();
        $withProducts->products()->attach($this->product('Snorkel')->id, ['is_automatic' => true]);
        $this->collection('Dacia Duster', 'dacia-duster');

        CollectionShowcase::forget();

        $shown = app(CollectionShowcase::class)->featured(12);

        $this->assertSame(['Suzuki Jimny'], $shown->pluck('name')->all());
    }

    public function test_a_featured_collection_wins_over_the_fallback(): void
    {
        $this->collection()->products()->attach($this->product('Snorkel')->id, ['is_automatic' => true]);
        $this->collection('Dacia Duster', 'dacia-duster')->update(['is_featured' => true]);

        CollectionShowcase::forget();

        $this->assertSame(['Dacia Duster'], app(CollectionShowcase::class)->featured(12)->pluck('name')->all());
    }

    public function test_the_index_groups_models_under_their_make(): void
    {
        $make = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Jimny', 'slug' => 'jimny', 'is_active' => true]);

        VehicleCollection::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'make_id' => $make->id, 'is_active' => true]);
        VehicleCollection::create([
            'name' => 'Suzuki Jimny', 'slug' => 'suzuki-jimny',
            'make_id' => $make->id, 'model_id' => $model->id, 'is_active' => true,
        ]);

        $this->get(route('storefront.collections'))
            ->assertOk()
            ->assertSee('Suzuki')
            ->assertSee('Suzuki Jimny');
    }

    /** The reason the garage image column exists: a saved car gets a picture. */
    public function test_saving_a_car_links_it_to_its_collection(): void
    {
        $make = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Jimny', 'slug' => 'jimny', 'is_active' => true]);
        $collection = VehicleCollection::create([
            'name' => 'Suzuki Jimny', 'slug' => 'suzuki-jimny',
            'make_id' => $make->id, 'model_id' => $model->id,
            'garage_image_path' => 'collections/jimny.jpg', 'is_active' => true,
        ]);

        $user = User::factory()->create();

        $vehicle = app(Garage::class)->add($user, [
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2010,
        ]);

        $this->assertSame($collection->id, $vehicle->vehicle_collection_id);
        $this->assertStringContainsString('collections/jimny.jpg', (string) $vehicle->collection->garageImageUrl());
    }

    /** A nickname says nothing about which car this is, so it must not clear the link. */
    public function test_a_partial_edit_leaves_the_collection_alone(): void
    {
        $make = VehicleMake::create(['name' => 'Suzuki', 'slug' => 'suzuki', 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Jimny', 'slug' => 'jimny', 'is_active' => true]);
        VehicleCollection::create([
            'name' => 'Suzuki Jimny', 'slug' => 'suzuki-jimny',
            'make_id' => $make->id, 'model_id' => $model->id, 'is_active' => true,
        ]);

        $garage = app(Garage::class);
        $user = User::factory()->create();
        $vehicle = $garage->add($user, ['make_id' => $make->id, 'model_id' => $model->id, 'year' => 2010]);

        $updated = $garage->update($vehicle, ['nickname' => 'Jimmy']);

        $this->assertNotNull($updated->vehicle_collection_id);
    }

    public function test_a_published_review_for_the_car_shows_on_the_collection_page(): void
    {
        $collection = $this->collection();

        Review::create([
            'vehicle_collection_id' => $collection->id,
            'reviewer_name' => 'Andrei M.',
            'body' => 'Kit montat fără probleme, merge excelent pe teren.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        Review::create([
            'vehicle_collection_id' => $collection->id,
            'reviewer_name' => 'Ciornă',
            'body' => 'Text care nu trebuie publicat.',
            'status' => 'draft',
        ]);

        $this->get($collection->url())
            ->assertOk()
            ->assertSee('Andrei M.')
            ->assertDontSee('Text care nu trebuie publicat.');
    }

    private function collection(string $name = 'Suzuki Jimny', string $slug = 'suzuki-jimny'): VehicleCollection
    {
        return VehicleCollection::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    private function product(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }
}
