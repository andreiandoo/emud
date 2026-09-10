<?php

namespace Tests\Feature;

use App\Models\VehicleCollection;
use App\Models\VehicleConfiguration;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Database\Seeders\VehicleCollectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleCollectionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_collection_for_each_make_and_model_with_a_real_vehicle(): void
    {
        $this->realVehicle('Suzuki', 'Jimny');

        $this->seed(VehicleCollectionSeeder::class);

        $this->assertTrue(VehicleCollection::query()->where('slug', 'suzuki')->exists());
        $this->assertTrue(VehicleCollection::query()->where('slug', 'suzuki-jimny')->exists());
    }

    /**
     * vPIC contributes every registered US manufacturer as reference data. A collection page for
     * a trailer builder that has never made a 4x4 is a page with nothing on it.
     */
    public function test_a_make_with_no_vehicle_behind_it_gets_no_collection(): void
    {
        VehicleMake::create(['name' => 'Acme Trailers', 'slug' => 'acme-trailers', 'is_active' => true]);

        $this->seed(VehicleCollectionSeeder::class);

        $this->assertFalse(VehicleCollection::query()->where('slug', 'acme-trailers')->exists());
    }

    public function test_the_model_collection_takes_its_years_from_the_generations(): void
    {
        $this->realVehicle('Suzuki', 'Jimny', 1998, 2018);

        $this->seed(VehicleCollectionSeeder::class);

        $collection = VehicleCollection::query()->where('slug', 'suzuki-jimny')->firstOrFail();

        $this->assertSame(1998, (int) $collection->year_from);
        $this->assertSame(2018, (int) $collection->year_to);
    }

    public function test_the_makes_this_shop_sells_for_are_featured(): void
    {
        $this->realVehicle('Suzuki', 'Jimny');
        $this->realVehicle('Ferrari', 'F40');

        $this->seed(VehicleCollectionSeeder::class);

        $this->assertTrue(VehicleCollection::query()->where('slug', 'suzuki')->value('is_featured'));
        $this->assertFalse((bool) VehicleCollection::query()->where('slug', 'ferrari')->value('is_featured'));
    }

    /** Re-running must not duplicate rows, and must not undo an operator's edits. */
    public function test_running_it_twice_leaves_existing_collections_untouched(): void
    {
        $this->realVehicle('Suzuki', 'Jimny');

        $this->seed(VehicleCollectionSeeder::class);

        $collection = VehicleCollection::query()->where('slug', 'suzuki-jimny')->firstOrFail();
        $collection->update(['name' => 'Jimny — tot ce ține de el', 'is_featured' => true]);

        $before = VehicleCollection::query()->count();

        $this->seed(VehicleCollectionSeeder::class);

        $this->assertSame($before, VehicleCollection::query()->count());
        $this->assertSame('Jimny — tot ce ține de el', $collection->fresh()->name);
        $this->assertTrue($collection->fresh()->is_featured);
    }

    private function realVehicle(string $makeName, string $modelName, int $from = 2000, ?int $to = 2010): void
    {
        $make = VehicleMake::firstOrCreate(
            ['slug' => str($makeName)->slug()->value()],
            ['name' => $makeName, 'is_active' => true],
        );

        $model = VehicleModel::create([
            'make_id' => $make->id,
            'name' => $modelName,
            'slug' => str($modelName)->slug()->value(),
            'is_active' => true,
        ]);

        $generation = VehicleGeneration::create([
            'model_id' => $model->id,
            'name' => 'I',
            'year_from' => $from,
            'year_to' => $to,
        ]);

        // withConfigurations() is what separates a car from a catalogue entry, so the fixture has
        // to have one for the seeder to see the model at all.
        VehicleConfiguration::create(['generation_id' => $generation->id, 'year' => $from]);
    }
}
