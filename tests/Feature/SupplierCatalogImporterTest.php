<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierProduct;
use App\Suppliers\Data\SupplierRecord;
use App\Suppliers\SupplierCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stages_and_auto_maps_a_supplier_product(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Demo Supplier',
            'code' => 'DEMO',
            'protocol' => 'json',
            'is_active' => true,
            'settings' => ['auto_create_products' => true],
        ]);

        $record = new SupplierRecord(
            externalId: 'A-100',
            name: 'Troliu 12V 5.4T',
            sku: 'WINCH-100',
            ean: '5940000000001',
            brand: 'Demo Winch',
            costPrice: 1200,
            recommendedRetailPrice: 1699.99,
            stockQuantity: 7,
            stockStatus: 'in_stock',
            raw: ['id' => 'A-100', 'stock' => 7],
        );

        $result = app(SupplierCatalogImporter::class)->import($supplier, $record, 'catalog');

        $this->assertTrue($result['created']);
        $supplierProduct = SupplierProduct::query()->firstOrFail();
        $this->assertSame('mapped', $supplierProduct->mapping_status);
        $this->assertNotNull($supplierProduct->product_id);
        $this->assertNotNull($supplierProduct->variant_id);
        $this->assertSame(7, SupplierOffer::query()->firstOrFail()->stock_quantity);
        $this->assertDatabaseHas('products', ['name' => 'Troliu 12V 5.4T', 'status' => 'review']);
    }
}
