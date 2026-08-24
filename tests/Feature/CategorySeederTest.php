<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_reference_catalog_as_a_hierarchy(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', ['full_path' => 'anvelope/anvelope-off-road', 'depth' => 1]);
        $this->assertDatabaseHas('categories', [
            'full_path' => 'camping-si-outdoor/corturi-de-acoperis-auto/corturi-de-acoperis-hard-top',
            'depth' => 2,
        ]);
        $this->assertGreaterThan(70, \App\Models\Category::query()->count());
    }
}
