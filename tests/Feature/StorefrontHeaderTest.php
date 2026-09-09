<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\HeaderSettings;
use App\Livewire\Storefront\VehicleSelector;
use App\Models\Category;
use App\Models\CustomerVehicle;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Settings\StoreSettings;
use App\Storefront\CategoryIcons;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_utility_bar_shows_the_configured_message_and_link(): void
    {
        app(StoreSettings::class)->put('header', [
            'topbar_message' => 'Transport gratuit peste 500 lei',
            'topbar_link_label' => 'Vezi condițiile',
            'topbar_link_url' => '/livrare',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Transport gratuit peste 500 lei')
            ->assertSee('Vezi condițiile')
            ->assertSee('/livrare');
    }

    public function test_the_dismissible_bar_is_absent_until_it_is_switched_on(): void
    {
        app(StoreSettings::class)->put('header', ['promo_enabled' => false, 'promo_message' => 'Reduceri de sezon']);

        $this->get('/')->assertOk()->assertDontSee('Reduceri de sezon');

        app(StoreSettings::class)->put('header', ['promo_enabled' => true, 'promo_message' => 'Reduceri de sezon']);

        $this->get('/')->assertOk()->assertSee('Reduceri de sezon');
    }

    /**
     * The stamp is what makes a dismissal specific to one campaign. Without it, a visitor who
     * closed the previous bar would never see the next one.
     */
    public function test_editing_the_message_changes_the_dismissal_stamp(): void
    {
        $settings = app(StoreSettings::class);

        Livewire::test(HeaderSettings::class)
            ->set('promo_enabled', true)
            ->set('promo_message', 'Prima campanie')
            ->call('save');

        $first = $settings->string('promo_version');

        Livewire::test(HeaderSettings::class)
            ->set('promo_enabled', true)
            ->set('promo_message', 'A doua campanie')
            ->call('save');

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, app(StoreSettings::class)->string('promo_version'));
    }

    public function test_the_mega_menu_shows_a_category_with_its_icon(): void
    {
        $top = Category::create([
            'name' => 'Suspensie', 'slug' => 'suspensie', 'full_path' => 'suspensie',
            'depth' => 0, 'is_active' => true, 'is_visible_in_menu' => true, 'icon' => 'suspension',
        ]);

        Category::create([
            'name' => 'Arcuri', 'slug' => 'arcuri', 'full_path' => 'suspensie/arcuri', 'parent_id' => $top->id,
            'depth' => 1, 'is_active' => true, 'is_visible_in_menu' => true,
        ]);

        $response = $this->get('/')->assertOk()->assertSee('Suspensie')->assertSee('Arcuri');

        // The drawing itself, not merely the key: a category whose icon does not resolve renders
        // an empty square, which looks like a broken image rather than a missing setting.
        $this->assertStringContainsString(
            $this->iconPath('suspension'),
            $response->getContent(),
        );
    }

    public function test_an_unknown_icon_key_falls_back_instead_of_rendering_nothing(): void
    {
        Category::create([
            'name' => 'Ceva', 'slug' => 'ceva', 'full_path' => 'ceva',
            'depth' => 0, 'is_active' => true, 'is_visible_in_menu' => true, 'icon' => 'nu-exista',
        ]);

        $this->assertStringContainsString($this->iconPath('part'), $this->get('/')->getContent());
    }

    public function test_every_offered_icon_has_a_drawing(): void
    {
        foreach (array_keys(CategoryIcons::OPTIONS) as $key) {
            $rendered = $this->iconPath($key);

            $this->assertNotSame(
                '',
                $rendered,
                "The back office offers the icon '{$key}' but the storefront has no path for it.",
            );

            if ($key !== 'part') {
                $this->assertNotSame(
                    $this->iconPath('part'),
                    $rendered,
                    "The icon '{$key}' silently falls back to the generic part.",
                );
            }
        }
    }

    public function test_a_guest_gets_the_make_and_model_cascade(): void
    {
        Livewire::test(VehicleSelector::class)
            ->assertSee('Autentifică-te')
            ->assertDontSee('Din garajul tău');
    }

    public function test_a_signed_in_customer_sees_their_garage_in_the_header(): void
    {
        $user = User::factory()->create();
        $vehicle = $this->garageVehicle($user, 'Suzuki', 'Jimny');

        Livewire::actingAs($user)
            ->test(VehicleSelector::class)
            ->assertSee('Din garajul tău')
            ->assertSee('Jimny')
            ->call('chooseFromGarage', $vehicle->id)
            ->assertDispatched('vehicle-changed');

        $this->assertSame($vehicle->id, app(VehicleContext::class)->current()?->customerVehicleId);
    }

    /**
     * The id arrives from the browser. Another customer's car carries their plate number and
     * service history, so resolving it by id alone would hand both over.
     */
    public function test_a_customer_cannot_select_someone_elses_car_from_the_header(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $vehicle = $this->garageVehicle($owner, 'Toyota', 'Hilux');

        try {
            Livewire::actingAs($intruder)
                ->test(VehicleSelector::class)
                ->call('chooseFromGarage', $vehicle->id);
            $this->fail('Another customer\'s car must not be selectable.');
        } catch (ModelNotFoundException) {
            // scoped to the signed-in customer, so it is simply not found
        }

        $this->assertNull(app(VehicleContext::class)->current()?->customerVehicleId);
    }

    public function test_the_header_selection_follows_a_change_made_elsewhere(): void
    {
        $component = Livewire::test(VehicleSelector::class)->assertSee('Mașina mea');

        app(VehicleContext::class)->select(new SelectedVehicle(1, 'Dacia', 2, 'Duster'));

        $component->dispatch('vehicle-changed')->assertSee('Dacia Duster');
    }

    private function iconPath(string $name): string
    {
        $svg = Blade::render('<x-storefront.icon :name="$name" />', ['name' => $name]);

        preg_match('/ d="([^"]+)"/', $svg, $matches);

        return $matches[1] ?? '';
    }

    private function garageVehicle(User $user, string $make, string $model): CustomerVehicle
    {
        $makeModel = VehicleMake::create(['name' => $make, 'slug' => strtolower($make), 'is_active' => true]);
        $modelModel = VehicleModel::create(['make_id' => $makeModel->id, 'name' => $model, 'slug' => strtolower($model)]);

        return CustomerVehicle::create([
            'user_id' => $user->id,
            'make_id' => $makeModel->id,
            'model_id' => $modelModel->id,
            'year' => 2018,
            'is_primary' => true,
        ]);
    }
}
