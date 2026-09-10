<?php

namespace Tests\Feature;

use App\Enums\SupplierProtocol;
use App\Livewire\Admin\Catalog\ProductEditor;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductEditorSupplierOffersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ranking suppliers by the price they quote picks the wrong one. A part quoted cheaper with
     * a dropship fee and freight on top routinely lands dearer than a pricier one that ships
     * free, and that is the whole reason this panel exists.
     */
    public function test_offers_are_ranked_by_what_the_part_actually_costs_at_the_door(): void
    {
        $product = $this->product();
        $this->offer($product, 'IEFTIN', ['cost_price' => 100, 'dropship_fee' => 25, 'shipping_cost_estimate' => 40]);
        $this->offer($product, 'SCUMP', ['cost_price' => 130, 'dropship_fee' => 0, 'shipping_cost_estimate' => 0]);

        $rows = $this->offersFor($product);

        $this->assertSame(['SCUMP', 'IEFTIN'], $rows->pluck('supplier.code')->all());
        $this->assertEqualsWithDelta(130.0, $rows->first()['landed']['unit_landed_cost'], 0.01);
        $this->assertEqualsWithDelta(165.0, $rows->last()['landed']['unit_landed_cost'], 0.01);
    }

    /**
     * An offer that cannot be landed must not sort as though it were free, and must say what is
     * missing — a dash in that column reads as "no extra cost".
     */
    public function test_an_offer_that_cannot_be_costed_sorts_last_and_names_what_is_missing(): void
    {
        $product = $this->product();
        $this->offer($product, 'COMPLET', ['cost_price' => 200]);
        $this->offer($product, 'INCOMPLET', ['cost_price' => null]);

        $rows = $this->offersFor($product);

        $this->assertSame(['COMPLET', 'INCOMPLET'], $rows->pluck('supplier.code')->all());
        $this->assertFalse($rows->last()['landed']['complete']);
        $this->assertContains('cost_price', $rows->last()['landed']['missing']);
    }

    public function test_dispatch_windows_are_read_from_whichever_field_the_supplier_filled(): void
    {
        $product = $this->product();
        $this->offer($product, 'INTERVAL', ['cost_price' => 10, 'dispatch_days_min' => 1, 'dispatch_days_max' => 3]);
        $this->offer($product, 'TERMEN', ['cost_price' => 20, 'lead_time_days' => 5]);

        $rows = $this->offersFor($product)->keyBy(fn (array $row): string => $row['supplier']->code);

        $this->assertSame('1–3 zile', $rows['INTERVAL']['dispatch']);
        $this->assertSame('5 zile', $rows['TERMEN']['dispatch']);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function offersFor(Product $product)
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return Livewire::actingAs($admin)
            ->test(ProductEditor::class, ['product' => $product])
            ->instance()
            ->supplierOffers;
    }

    /**
     * Re-read after creating: status, is_universal and is_featured get their values from
     * column defaults, which never reach the instance create() returns. The editor is
     * handed this instance directly, whereas a real request binds the row from the
     * database with every default in place.
     */
    private function product(): Product
    {
        return Product::query()->create(['name' => 'Bară față', 'slug' => 'bara-fata-'.uniqid()])->fresh();
    }

    /** @param array<string, mixed> $offer */
    private function offer(Product $product, string $code, array $offer): SupplierOffer
    {
        $supplier = Supplier::query()->create([
            'name' => $code,
            'code' => $code,
            'protocol' => SupplierProtocol::Csv,
            'default_currency' => 'RON',
            'is_active' => true,
        ]);

        $supplierProduct = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'external_id' => $code.'-1',
            'name' => $product->name,
        ]);

        return SupplierOffer::query()->create(array_merge([
            'supplier_product_id' => $supplierProduct->id,
            'currency' => 'RON',
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
        ], $offer));
    }
}
