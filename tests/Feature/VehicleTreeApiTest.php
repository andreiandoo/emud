<?php

namespace Tests\Feature;

use App\Models\CatalogSourceAssertion;
use App\Models\Category;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCatalogApiFixtures;
use Tests\TestCase;

class VehicleTreeApiTest extends TestCase
{
    use BuildsCatalogApiFixtures;
    use RefreshDatabase;

    public function test_the_cascade_walks_make_to_model_to_generation_to_vehicle(): void
    {
        $source = $this->apiSource();
        $vehicle = $this->apiVehicle($source, 'Land Rover', 'Defender', 'L663', 2022);
        $token = $this->apiToken();

        $makes = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/makes');
        $makes->assertOk();
        $this->assertSame('Land Rover', $makes->json('data.0.name'));

        $makeId = $vehicle->generation->model->make_id;
        $models = $this->withHeader('X-API-Key', $token)->getJson("/api/v1/makes/{$makeId}/models");
        $models->assertOk();
        $this->assertSame('Defender', $models->json('data.0.name'));
        $this->assertSame('Land Rover', $models->json('meta.make.name'));

        $modelId = $vehicle->generation->model_id;
        $generations = $this->withHeader('X-API-Key', $token)->getJson("/api/v1/models/{$modelId}/generations");
        $generations->assertOk();
        $this->assertSame('L663', $generations->json('data.0.name'));
        $this->assertSame(2020, $generations->json('data.0.year_from'));

        $generationId = $vehicle->generation_id;
        $vehicles = $this->withHeader('X-API-Key', $token)->getJson("/api/v1/generations/{$generationId}/vehicles");
        $vehicles->assertOk();
        $this->assertSame('veh_'.$vehicle->id, $vehicles->json('data.0.id'));
        $this->assertSame('AJ20D6', $vehicles->json('data.0.engine.code'));
    }

    public function test_a_make_with_no_publishable_vehicle_is_not_offered(): void
    {
        $source = $this->apiSource();
        $this->apiVehicle($source, 'Suzuki', 'Jimny', 'JB74', 2021);

        // vPIC contributes thousands of registered manufacturers with no car behind them. A
        // picker that lists them sends the customer down a branch that ends in nothing.
        VehicleMake::query()->create(['name' => 'Acme Welding', 'slug' => 'acme-welding', 'is_active' => true]);

        // A make that has a model but still no vehicle under it must be excluded too.
        $empty = VehicleMake::query()->create(['name' => 'Empty Motors', 'slug' => 'empty-motors', 'is_active' => true]);
        VehicleModel::query()->create(['make_id' => $empty->id, 'name' => 'Ghost', 'slug' => 'ghost', 'is_active' => true]);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/makes');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Suzuki', $names);
        $this->assertNotContains('Acme Welding', $names);
        $this->assertNotContains('Empty Motors', $names);
    }

    public function test_a_vehicle_whose_publication_was_withdrawn_drops_out_of_the_tree(): void
    {
        $source = $this->apiSource();
        $vehicle = $this->apiVehicle($source, 'Toyota', 'Hilux', 'AN120', 2020);
        $token = $this->apiToken();

        $this->assertCount(1, $this->withHeader('X-API-Key', $token)->getJson('/api/v1/makes')->json('data'));

        $source->update(['allow_api_redistribution' => false]);
        CatalogSourceAssertion::query()
            ->where('entity_type', 'vehicle_configuration')
            ->where('entity_id', $vehicle->id)
            ->update(['api_redistributable' => false]);

        $this->assertCount(0, $this->withHeader('X-API-Key', $token)->getJson('/api/v1/makes')->json('data'));
    }

    public function test_generations_can_be_narrowed_by_the_year_a_customer_knows(): void
    {
        $source = $this->apiSource();
        $old = $this->apiVehicle($source, 'Jeep', 'Wrangler', 'JK', 2010);
        $this->apiVehicle($source, 'Jeep', 'Wrangler', 'JL', 2022);

        $modelId = $old->generation->model_id;
        $response = $this->withHeader('X-API-Key', $this->apiToken())
            ->getJson("/api/v1/models/{$modelId}/generations?year=2010");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('JK', $response->json('data.0.name'));
    }

    public function test_the_make_list_can_be_searched_and_paged(): void
    {
        $source = $this->apiSource();
        foreach (['Alfa Romeo', 'Aston Martin', 'Audi', 'BMW'] as $index => $name) {
            $this->apiVehicle($source, $name, 'Model'.$index, 'Gen'.$index, 2020);
        }

        $token = $this->apiToken();

        $searched = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/makes?q=A');
        $searched->assertOk();
        $this->assertSame(3, $searched->json('meta.total'));

        $paged = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/makes?per_page=2&page=2');
        $paged->assertOk();
        $this->assertCount(2, $paged->json('data'));
        $this->assertSame(4, $paged->json('meta.total'));
    }

    public function test_the_category_tree_nests_and_can_be_returned_flat(): void
    {
        $parent = Category::query()->create([
            'name' => 'Brakes', 'slug' => 'brakes', 'full_path' => 'brakes', 'depth' => 0, 'position' => 1, 'is_active' => true,
        ]);
        Category::query()->create([
            'parent_id' => $parent->id, 'name' => 'Brake pads', 'slug' => 'brake-pads',
            'full_path' => 'brakes/brake-pads', 'depth' => 1, 'position' => 1, 'is_active' => true,
        ]);

        $token = $this->apiToken();

        $tree = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/categories');
        $tree->assertOk();
        $this->assertCount(1, $tree->json('data'));
        $this->assertSame('Brakes', $tree->json('data.0.name'));
        $this->assertSame('Brake pads', $tree->json('data.0.children.0.name'));

        $flat = $this->withHeader('X-API-Key', $token)->getJson('/api/v1/categories?flat=1');
        $flat->assertOk();
        $this->assertCount(2, $flat->json('data'));
        $this->assertSame('flat', $flat->json('meta.shape'));
    }

    public function test_with_parts_keeps_a_child_whose_parent_has_none(): void
    {
        $source = $this->apiSource();
        $parent = Category::query()->create([
            'name' => 'Filters', 'slug' => 'filters', 'full_path' => 'filters', 'depth' => 0, 'position' => 1, 'is_active' => true,
        ]);
        $child = Category::query()->create([
            'parent_id' => $parent->id, 'name' => 'Oil filters', 'slug' => 'oil-filters',
            'full_path' => 'filters/oil-filters', 'depth' => 1, 'position' => 1, 'is_active' => true,
        ]);
        $this->apiPart('Mahle', 'OC 90', $source, $child->id);

        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/categories?with_parts=1');

        $response->assertOk();
        // The parent holds no parts of its own, so it is filtered out — but the child that does
        // must still be reachable rather than disappearing with it.
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Oil filters', $response->json('data.0.name'));
    }

    public function test_an_unknown_branch_answers_in_the_error_envelope(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiToken())->getJson('/api/v1/makes/999999/models');

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('error.code'));
    }
}
