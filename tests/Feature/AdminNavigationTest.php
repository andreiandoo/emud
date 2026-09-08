<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A menu entry pointing at a route that does not exist throws while rendering the layout,
     * which would take down every admin page at once rather than just one link.
     */
    public function test_every_menu_route_exists(): void
    {
        $missing = array_values(array_filter(
            AdminNavigation::routeNames(),
            fn (string $name): bool => ! Route::has($name),
        ));

        $this->assertSame([], $missing);
    }

    public function test_no_route_is_listed_twice(): void
    {
        $routes = AdminNavigation::routeNames();

        $this->assertSame(array_values(array_unique($routes)), $routes);
    }

    public function test_every_group_has_a_label_and_items(): void
    {
        foreach (AdminNavigation::groups() as $group) {
            $this->assertNotSame('', $group['label']);
            $this->assertNotEmpty($group['items']);
        }
    }

    public function test_the_sidebar_renders_grouped_for_an_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('admin.dashboard'))->assertOk();

        foreach (AdminNavigation::groups() as $group) {
            $response->assertSee($group['label']);
        }
    }

    /**
     * The current page has to be identifiable in the menu, or an operator loses their place in
     * a list of thirty links.
     */
    public function test_the_current_page_is_marked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('admin.orders.index'))->assertSee('aria-current="page"', false);
    }

    public function test_the_admin_is_kept_out_of_search_engines(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('admin.dashboard'))->assertSee('content="noindex,nofollow"', false);
    }
}
