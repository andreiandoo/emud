<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Storefront\CategoryIcons;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_jobs_and_their_categories(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ServiceCatalogSeeder::class);

        $this->assertGreaterThan(10, ServiceCategory::query()->count());
        $this->assertGreaterThan(80, Service::query()->count());
        $this->assertTrue(Service::query()->where('slug', 'montaj-troliu')->exists());
    }

    /**
     * Every parts path in the table has to resolve. A mistyped one produces a service that links
     * to nothing and reports nothing — the whole point of the taxonomy lost silently, on one row.
     */
    public function test_every_parts_category_it_references_exists(): void
    {
        $this->seed(CategorySeeder::class);

        $missing = [];

        foreach (ServiceCatalogSeeder::CATALOGUE as $categoryName => $definition) {
            foreach ($definition['services'] as $serviceName => [$path, $duration]) {
                if ($path !== null && ! Category::query()->where('full_path', $path)->exists()) {
                    $missing[] = "{$categoryName} / {$serviceName} → {$path}";
                }
            }
        }

        $this->assertSame([], $missing, "Parts categories that do not exist:\n".implode("\n", $missing));
    }

    public function test_every_category_icon_is_one_the_storefront_can_draw(): void
    {
        foreach (ServiceCatalogSeeder::CATALOGUE as $categoryName => $definition) {
            $this->assertTrue(
                CategoryIcons::exists($definition['icon']),
                "The service category '{$categoryName}' asks for an icon the storefront does not have.",
            );
        }
    }

    /** Re-running a seeder must not duplicate what it already wrote. */
    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ServiceCatalogSeeder::class);

        $categories = ServiceCategory::query()->count();
        $services = Service::query()->count();

        $this->seed(ServiceCatalogSeeder::class);

        $this->assertSame($categories, ServiceCategory::query()->count());
        $this->assertSame($services, Service::query()->count());
    }
}
