<?php

namespace Tests\Feature;

use App\Livewire\Customer\Favourites;
use App\Livewire\Storefront\ProductPage;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\WishlistItem;
use App\Storefront\Compatibility\CompatibilityVerdict;
use App\Storefront\VehicleContext;
use App\Storefront\Wishlist;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerWishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_product_can_be_saved_to_the_account_list(): void
    {
        app(Wishlist::class)->add($this->user, $this->product());

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $this->user->id,
            'customer_vehicle_id' => null,
        ]);
    }

    /**
     * A customer with two cars should not get one undifferentiated list, so the same product
     * saved for two vehicles is two entries.
     */
    public function test_the_same_product_can_be_saved_for_two_vehicles(): void
    {
        $product = $this->product();
        $first = $this->garageVehicle();
        $second = $this->garageVehicle();

        app(Wishlist::class)->add($this->user, $product, $first);
        app(Wishlist::class)->add($this->user, $product, $second);

        $this->assertSame(2, WishlistItem::query()->count());
    }

    public function test_saving_the_same_product_to_the_same_list_twice_keeps_one_entry(): void
    {
        $product = $this->product();

        app(Wishlist::class)->add($this->user, $product);
        app(Wishlist::class)->add($this->user, $product, note: 'pentru primăvară');

        $item = WishlistItem::query()->sole();

        $this->assertSame('pentru primăvară', $item->note);
    }

    /**
     * NULLs compare as distinct inside a plain unique index, so without the COALESCE form the
     * account list could hold the same product many times over.
     */
    public function test_the_database_refuses_a_duplicate_account_entry(): void
    {
        $product = $this->product();
        $row = [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'customer_vehicle_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('wishlist_items')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('wishlist_items')->insert($row);
    }

    public function test_the_verdict_at_the_time_of_saving_is_recorded(): void
    {
        $vehicle = $this->garageVehicle();
        $product = $this->product();
        ProductFitment::create(['product_id' => $product->id, 'make_id' => $vehicle->make_id, 'model_id' => $vehicle->model_id]);

        app(Wishlist::class)->add($this->user, $product->fresh(), $vehicle);

        $this->assertSame(CompatibilityVerdict::Confirmed, WishlistItem::query()->sole()->verdict_when_saved);
    }

    /**
     * A catalogue correction must show as a change rather than silently rewriting what the
     * customer was told when they saved the part.
     */
    public function test_a_later_catalogue_change_is_reported_not_applied(): void
    {
        $vehicle = $this->garageVehicle();
        $product = $this->product();
        $fitment = ProductFitment::create(['product_id' => $product->id, 'make_id' => $vehicle->make_id, 'model_id' => $vehicle->model_id]);

        app(Wishlist::class)->add($this->user, $product->fresh(), $vehicle);

        $otherModel = VehicleModel::create(['make_id' => $vehicle->make_id, 'name' => 'Alt model', 'slug' => 'alt-'.Str::random(5)]);
        $fitment->update(['model_id' => $otherModel->id]);

        $item = WishlistItem::query()->with(['product.fitments', 'vehicle.make', 'vehicle.model'])->sole();

        $this->assertSame(CompatibilityVerdict::Confirmed, $item->verdict_when_saved);
        $this->assertTrue(app(Wishlist::class)->verdictChanged($item));
    }

    public function test_the_product_page_saves_and_unsaves(): void
    {
        $product = $this->product();

        Livewire::test(ProductPage::class, ['product' => $product])->call('toggleWishlist');
        $this->assertSame(1, WishlistItem::query()->count());

        Livewire::test(ProductPage::class, ['product' => $product])->call('toggleWishlist');
        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_saving_lands_on_the_active_garage_vehicles_list(): void
    {
        $vehicle = $this->garageVehicle();
        app(VehicleContext::class)->resetToGarage();

        Livewire::test(ProductPage::class, ['product' => $this->product()])->call('toggleWishlist');

        $this->assertSame($vehicle->id, WishlistItem::query()->sole()->customer_vehicle_id);
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_losing_the_action(): void
    {
        auth()->logout();

        Livewire::test(ProductPage::class, ['product' => $this->product()])
            ->call('toggleWishlist')
            ->assertRedirect(route('customer.login'));
    }

    public function test_the_account_list_and_a_vehicle_list_are_separate(): void
    {
        $vehicle = $this->garageVehicle();
        $accountProduct = $this->product();
        $vehicleProduct = $this->product();

        app(Wishlist::class)->add($this->user, $accountProduct);
        app(Wishlist::class)->add($this->user, $vehicleProduct, $vehicle);

        Livewire::test(Favourites::class)
            ->assertSee($accountProduct->name)
            ->assertDontSee($vehicleProduct->name)
            ->set('vehicle', (string) $vehicle->id)
            ->assertSee($vehicleProduct->name)
            ->assertDontSee($accountProduct->name);
    }

    public function test_an_item_can_be_moved_between_lists(): void
    {
        $vehicle = $this->garageVehicle();
        $item = app(Wishlist::class)->add($this->user, $this->product());

        Livewire::test(Favourites::class)->call('moveToVehicle', $item->id, $vehicle->id);

        $this->assertSame($vehicle->id, $item->refresh()->customer_vehicle_id);
    }

    /**
     * Moving onto a list that already holds the product would breach the uniqueness index, so
     * the duplicate is dropped rather than a constraint error reaching the customer.
     */
    public function test_moving_onto_a_list_that_already_has_it_merges_instead_of_failing(): void
    {
        $vehicle = $this->garageVehicle();
        $product = $this->product();
        $accountItem = app(Wishlist::class)->add($this->user, $product);
        app(Wishlist::class)->add($this->user, $product, $vehicle);

        Livewire::test(Favourites::class)->call('moveToVehicle', $accountItem->id, $vehicle->id);

        $this->assertSame(1, WishlistItem::query()->count());
        $this->assertDatabaseMissing('wishlist_items', ['id' => $accountItem->id]);
    }

    public function test_a_customer_cannot_remove_an_item_from_another_list(): void
    {
        $stranger = User::factory()->create();
        $foreign = app(Wishlist::class)->add($stranger, $this->product());

        Livewire::test(Favourites::class)->call('remove', $foreign->id);

        $this->assertDatabaseHas('wishlist_items', ['id' => $foreign->id]);
    }

    /**
     * Removing a car should demote its saved parts to the account list rather than throwing
     * away things the customer chose to keep.
     */
    public function test_deleting_a_vehicle_keeps_its_saved_parts(): void
    {
        $vehicle = $this->garageVehicle();
        app(Wishlist::class)->add($this->user, $this->product(), $vehicle);

        $vehicle->delete();

        $this->assertSame(1, WishlistItem::query()->count());
        $this->assertNull(WishlistItem::query()->sole()->customer_vehicle_id);
    }

    private function product(): Product
    {
        $product = Product::create([
            'name' => 'Produs '.Str::random(6),
            'slug' => 'produs-'.Str::random(8),
            'status' => 'active',
            'published_at' => now(),
        ]);

        ProductVariant::create(['product_id' => $product->id, 'sku' => 'V-'.Str::random(8), 'retail_price' => '99.99', 'is_active' => true]);

        return $product->refresh();
    }

    private function garageVehicle(): CustomerVehicle
    {
        $make = VehicleMake::create(['name' => 'Marca '.Str::random(4), 'slug' => 'marca-'.Str::random(6)]);
        $model = VehicleModel::create(['make_id' => $make->id, 'name' => 'Model', 'slug' => 'model-'.Str::random(6)]);

        return CustomerVehicle::create([
            'user_id' => $this->user->id,
            'make_id' => $make->id,
            'model_id' => $model->id,
            'year' => 2020,
            'is_primary' => ! $this->user->vehicles()->exists(),
        ]);
    }
}
