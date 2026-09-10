<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Livewire\Storefront\CollectionsIndex;
use App\Models\Product;
use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Storefront\CatalogMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The collections wall.
 *
 * The page exists to be searched, not read: with several hundred collections seeded from the
 * vehicle graph, printing the lot as text is both useless to a visitor and a page weighing half
 * a megabyte. What is not on the first screenful has to be reachable through the box.
 */
class CollectionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_search_narrows_the_wall_without_a_page_load(): void
    {
        $this->collection('Dacia Duster', 'dacia-duster');
        $this->collection('Suzuki Jimny', 'suzuki-jimny');

        Livewire::test(CollectionsIndex::class)
            ->assertSee('Dacia Duster')
            ->assertSee('Suzuki Jimny')
            ->set('search', 'jimny')
            ->assertSee('Suzuki Jimny')
            ->assertDontSee('Dacia Duster')
            ->call('clearSearch')
            ->assertSee('Dacia Duster');
    }

    public function test_the_search_matches_part_of_a_name_regardless_of_case(): void
    {
        $this->collection('Dacia Duster', 'dacia-duster');

        Livewire::test(CollectionsIndex::class)
            ->set('search', 'DUST')
            ->assertSee('Dacia Duster');
    }

    /** Only a screenful is rendered; the rest arrives on demand rather than never. */
    public function test_the_wall_loads_in_screenfuls(): void
    {
        foreach (range(1, 42) as $index) {
            $this->collection('Model '.$index, 'model-'.$index);
        }

        $component = Livewire::test(CollectionsIndex::class);

        $this->assertCount(30, $component->viewData('tiles'));
        $this->assertTrue($component->viewData('hasMore'));

        $component->call('loadMore');

        $this->assertCount(42, $component->viewData('tiles'));
        $this->assertFalse($component->viewData('hasMore'));
    }

    /** A new search must not inherit however far the previous one had been scrolled. */
    public function test_searching_again_starts_the_wall_from_the_top(): void
    {
        foreach (range(1, 42) as $index) {
            $this->collection('Model '.$index, 'model-'.$index);
        }

        Livewire::test(CollectionsIndex::class)
            ->call('loadMore')
            ->assertSet('perPage', 60)
            ->set('search', 'model 1')
            ->assertSet('perPage', 30);
    }

    /** Collections that have a photograph lead: this page is the pictures. */
    public function test_a_collection_with_a_photograph_comes_first(): void
    {
        $this->collection('Aaa Fără poză', 'aaa-fara-poza');
        $this->collection('Zzz Cu poză', 'zzz-cu-poza')->update(['square_image_path' => 'collections/z.jpg']);

        $names = Livewire::test(CollectionsIndex::class)->viewData('tiles')->pluck('name')->all();

        $this->assertSame(['Zzz Cu poză', 'Aaa Fără poză'], $names);
    }

    public function test_the_metrics_report_what_the_catalogue_holds(): void
    {
        $this->collection('Dacia Duster', 'dacia-duster');
        $this->realVehicle();
        Product::create([
            'name' => 'Snorkel', 'slug' => 'snorkel',
            'status' => ProductStatus::Active, 'published_at' => now(),
        ]);

        CatalogMetrics::forget();

        $metrics = app(CatalogMetrics::class)->snapshot();

        $this->assertSame(1, $metrics['collections']['value']);
        $this->assertSame(1, $metrics['models']['value']);
        $this->assertSame(1, $metrics['products']['value']);
    }

    /**
     * A metric that counts nothing is dropped rather than shown as a zero: "0 repere în catalogul
     * tehnic" advertises an empty shop, which on a fresh install is exactly what it would say.
     */
    public function test_a_metric_that_counts_nothing_is_not_shown(): void
    {
        $this->collection('Dacia Duster', 'dacia-duster');

        CatalogMetrics::forget();

        $metrics = Livewire::test(CollectionsIndex::class)->viewData('metrics');

        $this->assertArrayHasKey('collections', $metrics);
        $this->assertArrayNotHasKey('parts', $metrics);
    }

    public function test_a_hidden_collection_never_reaches_the_wall(): void
    {
        $this->collection('Dacia Duster', 'dacia-duster')->update(['is_active' => false]);

        Livewire::test(CollectionsIndex::class)->assertDontSee('Dacia Duster');
    }

    private function collection(string $name, string $slug): VehicleCollection
    {
        return VehicleCollection::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    private function realVehicle(): void
    {
        $make = VehicleMake::create(['name' => 'Dacia', 'slug' => 'dacia', 'is_active' => true]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Duster', 'slug' => 'duster', 'is_active' => true]);
        $generation = VehicleGeneration::create(['model_id' => $model->id, 'name' => 'II', 'year_from' => 2018]);

        VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => 2019]);
    }
}
