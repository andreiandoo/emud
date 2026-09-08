<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Storefront\CategoryMenu;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontMegaMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_is_three_levels_deep(): void
    {
        $this->taxonomy();

        $tree = app(CategoryMenu::class)->tree();
        $top = $tree->firstWhere('name', 'Suspensie');

        $this->assertNotNull($top);
        $this->assertSame('Arcuri', $top['children']->first()['name']);
        $this->assertSame('Arcuri spate', $top['children']->first()['children']->first()['name']);
    }

    public function test_the_fourth_level_is_not_rendered(): void
    {
        $this->taxonomy();
        $leaf = Category::query()->where('full_path', 'suspensie/arcuri/arcuri-spate')->sole();
        $this->category('Prea adânc', 'suspensie/arcuri/arcuri-spate/prea-adanc', $leaf, depth: 3);

        $tree = app(CategoryMenu::class)->tree();
        $third = $tree->firstWhere('name', 'Suspensie')['children']->first()['children']->first();

        $this->assertTrue($third['children']->isEmpty());
    }

    public function test_hidden_and_inactive_categories_stay_out(): void
    {
        $this->taxonomy();
        $this->category('Ascunsă', 'ascunsa', null, depth: 0, visible: false);
        $this->category('Inactivă', 'inactiva', null, depth: 0, active: false);

        $names = app(CategoryMenu::class)->tree()->pluck('name');

        $this->assertFalse($names->contains('Ascunsă'));
        $this->assertFalse($names->contains('Inactivă'));
    }

    public function test_the_whole_menu_costs_a_single_query(): void
    {
        $this->taxonomy();
        app(CategoryMenu::class)->tree();

        // Cleared so the count measures building the tree, not reading the cache.
        CategoryMenu::forget();

        DB::enableQueryLog();
        app(CategoryMenu::class)->tree();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'The menu renders on every page; a query per level would be a round trip per branch.');
    }

    public function test_the_second_call_is_served_from_cache(): void
    {
        $this->taxonomy();
        app(CategoryMenu::class)->tree();

        DB::enableQueryLog();
        app(CategoryMenu::class)->tree();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    /**
     * The menu is on every page, so a stale copy is wrong everywhere at once.
     */
    public function test_changing_the_taxonomy_clears_the_cache(): void
    {
        $this->taxonomy();
        app(CategoryMenu::class)->tree();

        $this->category('Nouă', 'noua', null, depth: 0);

        $this->assertTrue(app(CategoryMenu::class)->tree()->pluck('name')->contains('Nouă'));
    }

    public function test_deleting_a_category_clears_the_cache(): void
    {
        $this->taxonomy();
        app(CategoryMenu::class)->tree();

        Category::query()->where('full_path', 'suspensie/arcuri/arcuri-spate')->sole()->delete();

        $tree = app(CategoryMenu::class)->tree();

        $this->assertTrue($tree->firstWhere('name', 'Suspensie')['children']->first()['children']->isEmpty());
    }

    public function test_the_menu_renders_on_the_storefront(): void
    {
        $this->taxonomy();

        $this->get('/')
            ->assertOk()
            ->assertSee('Suspensie')
            ->assertSee('Arcuri')
            ->assertSee('Arcuri spate');
    }

    public function test_the_seeded_taxonomy_produces_a_usable_menu(): void
    {
        $this->seed(CategorySeeder::class);

        $tree = app(CategoryMenu::class)->tree();

        $this->assertGreaterThan(0, $tree->count());
        $this->assertGreaterThan(0, $tree->sum(fn (array $top) => $top['children']->count()));
    }

    private function taxonomy(): void
    {
        $top = $this->category('Suspensie', 'suspensie', null, depth: 0);
        $group = $this->category('Arcuri', 'suspensie/arcuri', $top, depth: 1);
        $this->category('Arcuri spate', 'suspensie/arcuri/arcuri-spate', $group, depth: 2);
    }

    private function category(string $name, string $path, ?Category $parent, int $depth, bool $visible = true, bool $active = true): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => str_contains($path, '/') ? substr($path, strrpos($path, '/') + 1) : $path,
            'full_path' => $path,
            'parent_id' => $parent?->id,
            'depth' => $depth,
            'is_active' => $active,
            'is_visible_in_menu' => $visible,
        ]);
    }
}
