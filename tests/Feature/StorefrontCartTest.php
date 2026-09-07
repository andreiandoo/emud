<?php

namespace Tests\Feature;

use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\ProductPage;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Storefront\CartManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class StorefrontCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_product_snapshots_the_price_from_the_database(): void
    {
        $product = $this->product(price: 249.50);

        $item = app(CartManager::class)->add($product, null, 2);

        $this->assertSame(249.5, (float) $item->unit_price);
        $this->assertSame(2, $item->quantity);
    }

    /**
     * A later catalogue edit must not silently change what the customer thought they were
     * buying; the difference has to remain visible rather than being applied behind their back.
     */
    public function test_a_later_price_change_does_not_rewrite_the_line(): void
    {
        $product = $this->product(price: 100.00);
        $item = app(CartManager::class)->add($product, null, 1);

        $product->variants()->first()->update(['retail_price' => 180.00]);

        // Compared numerically: the decimal cast renders differently on SQLite and PostgreSQL.
        $this->assertSame(100.0, (float) $item->refresh()->unit_price);
    }

    public function test_adding_the_same_product_twice_increments_one_line(): void
    {
        $product = $this->product();
        $carts = app(CartManager::class);

        $carts->add($product, null, 1);
        $carts->add($product, null, 3);

        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(4, CartItem::query()->sole()->quantity);
    }

    public function test_a_product_without_a_price_cannot_be_added(): void
    {
        $product = Product::create(['name' => 'Fără preț', 'slug' => 'fara-pret-'.Str::random(5), 'status' => 'active', 'published_at' => now()]);

        $this->expectException(RuntimeException::class);

        app(CartManager::class)->add($product, null, 1);
    }

    /**
     * NULLs compare as distinct inside a unique index, so the pre-existing constraint allowed a
     * product added without a variant to appear on several lines and be counted more than once.
     */
    public function test_the_database_refuses_a_second_line_for_a_product_without_a_variant(): void
    {
        $product = $this->product();
        $cart = Cart::create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);

        $line = ['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'unit_price' => 10, 'created_at' => now(), 'updated_at' => now()];
        DB::table('cart_items')->insert($line);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('cart_items')->insert($line);
    }

    public function test_signing_in_keeps_the_basket_assembled_beforehand(): void
    {
        $product = $this->product();
        app(CartManager::class)->add($product, null, 2);

        Auth::login(User::factory()->create());

        $this->assertSame(2, app(CartManager::class)->current()?->items()->sum('quantity'));
    }

    /**
     * An account that already had a basket must not lose it when a guest basket arrives, so the
     * two are merged rather than one replacing the other.
     */
    public function test_signing_in_merges_both_baskets(): void
    {
        $user = User::factory()->create();
        $accountProduct = $this->product();
        $guestProduct = $this->product();

        $this->actingAs($user);
        app(CartManager::class)->add($accountProduct, null, 1);

        Auth::logout();
        session()->flush();
        app(CartManager::class)->add($guestProduct, null, 3);

        Auth::login($user);

        $cart = app(CartManager::class)->current();

        $this->assertSame(2, $cart?->items()->count());
        $this->assertSame(4, (int) $cart->items()->sum('quantity'));
    }

    public function test_the_same_product_in_both_baskets_is_summed_once(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $this->actingAs($user);
        app(CartManager::class)->add($product, null, 1);

        Auth::logout();
        session()->flush();
        app(CartManager::class)->add($product, null, 2);

        Auth::login($user);

        $cart = app(CartManager::class)->current();

        $this->assertSame(1, $cart?->items()->count());
        $this->assertSame(3, (int) $cart->items()->sum('quantity'));
    }

    public function test_the_product_page_adds_to_the_cart(): void
    {
        $product = $this->product();

        Livewire::test(ProductPage::class, ['product' => $product])
            ->set('quantity', 2)
            ->call('addToCart')
            ->assertHasNoErrors();

        $this->assertSame(2, (int) CartItem::query()->sole()->quantity);
    }

    public function test_setting_a_quantity_to_zero_removes_the_line(): void
    {
        $product = $this->product();
        $item = app(CartManager::class)->add($product, null, 2);

        Livewire::test(CartPage::class)->call('setQuantity', $item->id, 0);

        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * Line ids come from the page, so a customer must not be able to reach into a basket that
     * is not theirs by editing one.
     */
    public function test_a_visitor_cannot_change_a_line_in_someone_elses_basket(): void
    {
        $product = $this->product();
        $foreign = Cart::create(['token' => (string) Str::uuid(), 'status' => 'active', 'currency' => 'RON']);
        $foreignItem = $foreign->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]);

        app(CartManager::class)->add($this->product(), null, 1);

        try {
            Livewire::test(CartPage::class)->call('remove', $foreignItem->id);
            $this->fail('Reaching into another basket should not be possible.');
        } catch (ModelNotFoundException) {
            // The lookup is scoped to the visitor's own cart, so the line is simply not found.
        }

        $this->assertDatabaseHas('cart_items', ['id' => $foreignItem->id]);
    }

    private function product(float $price = 199.99): Product
    {
        $product = Product::create([
            'name' => 'Produs '.Str::random(5),
            'slug' => 'produs-'.Str::random(8),
            'status' => 'active',
            'published_at' => now(),
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'V-'.Str::random(8),
            'retail_price' => $price,
            'is_active' => true,
        ]);

        return $product->refresh();
    }
}
