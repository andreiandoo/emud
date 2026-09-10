<?php

namespace Tests\Feature;

use App\Commerce\PricingPolicy;
use App\Commerce\Repricer;
use App\Enums\PriceChangeStatus;
use App\Enums\PricingMode;
use App\Enums\PricingScope;
use App\Enums\StockStatus;
use App\Jobs\EvaluateProductAlerts;
use App\Jobs\RepriceProduct;
use App\Livewire\Admin\Catalog\ProductEditor;
use App\Livewire\Admin\Pricing\PricingRulesIndex;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceChange;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PricingRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ShelfPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the payment fee is left in, so every expected price below can be checked by
        // hand: 2% of the gross, VAT at 21%, prices ending in .99.
        config([
            'emud.catalog.default_vat_rate' => 21,
            'emud.pricing.price_ending' => 0.99,
            'emud.pricing.default_target_margin_percent' => 25,
            'emud.pricing.minimum_contribution_percent' => 8,
            'emud.pricing.max_auto_change_percent' => 15,
            'emud.pricing.reference_destination' => 'RO',
            'emud.pricing.margins' => [
                'payment_fee_percent' => 2.0,
                'payment_fee_fixed' => 0.0,
                'return_rate_percent' => 0.0,
                'return_handling_cost' => 0.0,
                'bulky_return_multiplier' => 1.0,
                'warranty_reserve_percent' => 0.0,
            ],
        ]);
    }

    public function test_the_shelf_price_is_the_rule_margin_on_the_landed_cost(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: null);
        $this->offer($product, ['cost_price' => 100]);

        [$change] = $this->reprice($product);

        // 100 / (1 - 0.25) = 133.33 net, x 1.21 VAT = 161.33, up to 161.99.
        $this->assertSame(PriceChangeStatus::Applied, $change->status);
        $this->assertSame('161.99', $variant->fresh()->retail_price);
        $this->assertSame('target_margin', $change->decision['binding']);
    }

    public function test_the_contribution_floor_lifts_a_margin_that_would_leave_too_little(): void
    {
        $this->defaultRule(2);
        [$product, $variant] = $this->product(price: null);
        $this->offer($product, ['cost_price' => 100]);

        [$change] = $this->reprice($product);

        // 2% margin would give 123.47. Keeping 8% of net after the 2% fee needs
        // 100 / (0.92 / 1.21 - 0.02) = 135.08, which rounds up to 135.99.
        $this->assertSame('135.99', $variant->fresh()->retail_price);
        $this->assertSame('minimum_contribution', $change->decision['binding']);
        $this->assertGreaterThanOrEqual(8.0, $change->decision['contribution_percent']);
    }

    public function test_the_advertised_minimum_is_respected(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: null);
        $this->offer($product, ['cost_price' => 100, 'map_price' => 200]);

        [$change] = $this->reprice($product);

        $this->assertSame('200.99', $variant->fresh()->retail_price);
        $this->assertSame('map', $change->decision['binding']);
    }

    public function test_the_most_specific_rule_governs_the_product(): void
    {
        $this->defaultRule(25, minimumContribution: 10);
        $parent = $this->category('Iluminare');
        $child = $this->category('Bare LED', $parent);
        $brand = Brand::query()->create(['name' => 'Lazer', 'slug' => 'lazer']);
        $supplier = $this->supplier('LUMINI');

        [$product] = $this->product(price: null, brand: $brand);
        $product->categories()->attach($child->id, ['is_primary' => true]);

        $this->rule(PricingScope::Category, $parent->id, 45);
        $policy = app(PricingPolicy::class)->for($product, $supplier);
        $this->assertSame(45.0, $policy['target_margin_percent'], 'A rule on the parent covers every subcategory below it.');
        $this->assertSame(10.0, $policy['minimum_contribution_percent'], 'Guardrails the rule does not state come from the default rule.');

        $this->rule(PricingScope::Category, $child->id, 50);
        $this->assertSame(50.0, app(PricingPolicy::class)->for($product, $supplier)['target_margin_percent']);

        $this->rule(PricingScope::Supplier, $supplier->id, 35);
        $this->assertSame(35.0, app(PricingPolicy::class)->for($product, $supplier)['target_margin_percent']);

        $this->rule(PricingScope::Brand, $brand->id, 40);
        $this->assertSame('brand', app(PricingPolicy::class)->for($product, $supplier)['scope']);
    }

    public function test_a_small_move_goes_live_and_a_large_one_waits_for_a_person(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: 161.99);
        $offer = $this->offer($product, ['cost_price' => 105]);

        [$small] = $this->reprice($product);
        $this->assertSame(PriceChangeStatus::Applied, $small->status, '161.99 to 169.99 is under 5%.');
        $this->assertSame('169.99', $variant->fresh()->retail_price);

        $offer->update(['cost_price' => 150]);
        [$large] = $this->reprice($product);

        $this->assertSame(PriceChangeStatus::Pending, $large->status, 'A 43% jump must not go live unseen.');
        $this->assertSame('242.99', $large->new_price);
        $this->assertSame('169.99', $variant->fresh()->retail_price);
    }

    public function test_a_newer_proposal_replaces_the_one_still_waiting(): void
    {
        $this->defaultRule(25);
        [$product] = $this->product(price: 161.99);
        $offer = $this->offer($product, ['cost_price' => 150]);

        [$first] = $this->reprice($product);
        $offer->update(['cost_price' => 160]);
        [$second] = $this->reprice($product);

        $this->assertSame(PriceChangeStatus::Superseded, $first->fresh()->status);
        $this->assertSame(PriceChangeStatus::Pending, $second->status);
        $this->assertSame('258.99', $second->new_price);
    }

    public function test_approving_puts_the_price_live_and_rejecting_keeps_the_old_one(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: 161.99);
        $offer = $this->offer($product, ['cost_price' => 150]);

        [$pending] = $this->reprice($product);
        app(Repricer::class)->reject($pending);
        $this->assertSame('161.99', $variant->fresh()->retail_price);

        $offer->update(['cost_price' => 151]);
        [$again] = $this->reprice($product);
        app(Repricer::class)->approve($again);

        $this->assertSame(PriceChangeStatus::Applied, $again->fresh()->status);
        $this->assertSame($again->new_price, $variant->fresh()->retail_price);
    }

    public function test_a_manually_priced_product_is_never_touched(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: 99.99);
        $product->update(['pricing_mode' => PricingMode::Manual]);
        $this->offer($product, ['cost_price' => 100]);

        $this->assertSame([], $this->reprice($product->fresh()));
        $this->assertSame('99.99', $variant->fresh()->retail_price);
    }

    public function test_an_offer_whose_cost_cannot_be_completed_leaves_the_price_alone(): void
    {
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: 120.00);
        $this->offer($product, ['cost_price' => 100, 'shipping_cost_estimate' => null]);

        $this->assertSame([], $this->reprice($product));
        $this->assertSame(0, PriceChange::query()->count());
    }

    public function test_the_job_reprices_and_wakes_the_price_alerts(): void
    {
        Bus::fake([EvaluateProductAlerts::class]);
        $this->defaultRule(25);
        [$product, $variant] = $this->product(price: null);
        $this->offer($product, ['cost_price' => 100]);

        (new RepriceProduct($product->id))->handle(app(Repricer::class));

        $this->assertSame('161.99', $variant->fresh()->retail_price);
        Bus::assertDispatched(EvaluateProductAlerts::class);
    }

    public function test_the_nightly_command_needs_a_scope_and_then_reprices(): void
    {
        $this->defaultRule(25);
        [$product] = $this->product(price: null);
        $this->offer($product, ['cost_price' => 100]);

        $this->artisan('pricing:reprice')->assertExitCode(2);
        $this->artisan('pricing:reprice --all')->expectsOutputToContain('Prețuri aplicate: 1')->assertSuccessful();
    }

    public function test_the_seeded_policy_attaches_to_the_real_categories_and_keeps_edits(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(PricingRuleSeeder::class);

        $default = PricingRule::query()->where('scope_type', PricingScope::Default->value)->sole();
        $this->assertSame('25.00', $default->target_gross_margin_percent);
        $this->assertSame('8.00', $default->minimum_contribution_percent);

        $lighting = Category::query()->where('full_path', 'iluminare')->sole();
        $recovery = Category::query()->where('full_path', 'trolii-si-recuperare/accesorii-recuperare')->sole();
        $this->assertSame('45.00', PricingRule::query()->where('scope_id', $lighting->id)->sole()->target_gross_margin_percent);
        $this->assertSame('40.00', PricingRule::query()->where('scope_id', $recovery->id)->sole()->target_gross_margin_percent);

        PricingRule::query()->where('scope_id', $lighting->id)->update(['target_gross_margin_percent' => 55]);
        $this->seed(PricingRuleSeeder::class);

        $this->assertSame('55.00', PricingRule::query()->where('scope_id', $lighting->id)->sole()->target_gross_margin_percent, 'Re-seeding must not undo an operator edit.');
        $this->assertSame(8, PricingRule::query()->count());
    }

    public function test_the_pricing_screens_render_and_a_rule_can_be_saved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->defaultRule(25);
        [$product] = $this->product(price: 161.99);
        $this->offer($product, ['cost_price' => 150]);
        $this->reprice($product);

        $this->actingAs($admin)->get(route('admin.pricing.rules'))->assertOk()->assertSee('Reguli de preț');
        $this->actingAs($admin)->get(route('admin.pricing.changes'))->assertOk()->assertSee('Bară față');

        $category = $this->category('Anvelope');
        Livewire::actingAs($admin)->test(PricingRulesIndex::class)
            ->set('scopeType', 'category')
            ->set('scopeId', $category->id)
            ->set('targetMargin', '15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('15.00', PricingRule::query()->where('scope_id', $category->id)->sole()->target_gross_margin_percent);

        Livewire::actingAs($admin)->test(ProductEditor::class, ['product' => $product->fresh()])
            ->assertSet('pricingMode', 'auto');
    }

    /** @return list<PriceChange> */
    private function reprice(Product $product): array
    {
        // Fresh each time, as a queued job would be: the policy memoises the rules it read.
        return app(Repricer::class)->reprice($product, 'test');
    }

    private function defaultRule(float $margin, ?float $minimumContribution = null): PricingRule
    {
        return $this->rule(PricingScope::Default, null, $margin, $minimumContribution);
    }

    private function rule(PricingScope $scope, ?int $scopeId, float $margin, ?float $minimumContribution = null): PricingRule
    {
        return PricingRule::query()->create([
            'scope_type' => $scope,
            'scope_id' => $scopeId,
            'target_gross_margin_percent' => $margin,
            'minimum_contribution_percent' => $minimumContribution,
        ]);
    }

    /** @return array{0: Product, 1: ProductVariant} */
    private function product(?float $price, ?Brand $brand = null): array
    {
        $product = Product::query()->create([
            'name' => 'Bară față',
            'slug' => 'bara-fata-'.Str::random(8),
            'brand_id' => $brand?->id,
        ])->fresh();

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'V-'.Str::random(8),
            'retail_price' => $price,
            'currency' => 'RON',
            'is_active' => true,
        ]);

        return [$product, $variant];
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        $slug = Str::slug($name);

        return Category::query()->create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'full_path' => $parent ? "{$parent->full_path}/{$slug}" : $slug,
            'depth' => $parent ? $parent->depth + 1 : 0,
        ]);
    }

    private function supplier(string $code): Supplier
    {
        return Supplier::query()->create(['name' => $code, 'code' => $code, 'protocol' => 'csv', 'default_currency' => 'RON', 'is_active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function offer(Product $product, array $attributes): SupplierOffer
    {
        $supplierProduct = SupplierProduct::query()->create([
            'supplier_id' => $this->supplier('FURNIZOR-'.Str::random(4))->id,
            'product_id' => $product->id,
            'external_id' => 'EXT-'.Str::random(8),
            'name' => $product->name,
        ]);

        return SupplierOffer::query()->create([
            'supplier_product_id' => $supplierProduct->id,
            'currency' => 'RON',
            'stock_status' => StockStatus::InStock,
            'stock_quantity' => 10,
            'shipping_cost_estimate' => 0,
            'dispatch_days_max' => 2,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
