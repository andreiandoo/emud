<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Settings\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page states its own title.
 *
 * Until this existed the layout printed one <title> for the whole shop, taken from the tagline —
 * so a search engine saw eleven thousand collection pages all called the same thing, which is
 * about the worst signal a catalogue can send.
 */
class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(StoreSettings::class)->put('general', [
            'site_title' => 'eMUD',
            'site_tagline' => 'Piese și accesorii 4x4',
        ]);
    }

    public function test_a_collection_page_titles_itself(): void
    {
        $collection = VehicleCollection::create([
            'name' => 'Suzuki Jimny',
            'slug' => 'suzuki-jimny',
            'seo_title' => 'Piese și accesorii Suzuki Jimny',
            'is_active' => true,
        ]);

        $this->get($collection->url())
            ->assertOk()
            ->assertSee('<title>Piese și accesorii Suzuki Jimny · eMUD</title>', false);
    }

    /** With no SEO title written, the collection's own name still beats the shop tagline. */
    public function test_a_collection_without_seo_copy_falls_back_to_its_name(): void
    {
        $collection = VehicleCollection::create(['name' => 'Dacia Duster', 'slug' => 'dacia-duster', 'is_active' => true]);

        $this->get($collection->url())
            ->assertOk()
            ->assertSee('<title>Dacia Duster · eMUD</title>', false);
    }

    public function test_a_product_page_titles_itself(): void
    {
        $product = Product::create([
            'name' => 'Snorkel Dacia Bigster',
            'slug' => 'snorkel-dacia-bigster',
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);

        $this->get(route('storefront.product', $product))
            ->assertOk()
            ->assertSee('<title>Snorkel Dacia Bigster · eMUD</title>', false);
    }

    /** The tagline is only the fallback; even the home page states a title of its own. */
    public function test_the_home_page_titles_itself(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Piese și accesorii 4x4, off-road și overlanding · eMUD</title>', false);
    }

    public function test_the_collections_index_titles_itself(): void
    {
        $this->get(route('storefront.collections'))
            ->assertOk()
            ->assertSee('<title>Colecții pe model de mașină · eMUD</title>', false);
    }
}
