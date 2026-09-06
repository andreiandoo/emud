<?php

namespace Tests\Unit\Suppliers;

use App\Models\Supplier;
use App\Suppliers\Parsing\SupplierRecordMapper;
use Tests\TestCase;

class SupplierRecordMapperTest extends TestCase
{
    public function test_it_maps_localized_numbers_statuses_and_delimited_values(): void
    {
        $supplier = new Supplier([
            'default_currency' => 'EUR',
            'field_mapping' => [
                'external_id' => 'article.id',
                'name' => 'article.name',
                'manufacturer_part_number' => 'article.mpn',
                'cost_price' => 'commercial.cost',
                'stock_quantity' => 'commercial.qty',
                'stock_status' => 'commercial.status',
                'images' => 'media.images',
                'fitments' => 'applications',
                'oe_numbers' => 'references.oe',
                'iam_numbers' => 'references.iam',
                'cross_references' => 'references.cross',
                'supersessions' => 'references.supersessions',
            ],
            'settings' => [
                'number_thousands_separator' => '.',
                'number_decimal_separator' => ',',
                'stock_status_map' => ['lagernd' => 'in_stock'],
                'images_delimiter' => '|',
                'fitments_delimiter' => ';',
                'iam_numbers_delimiter' => '|',
            ],
        ]);

        $record = (new SupplierRecordMapper)->map($supplier, [
            'article' => ['id' => 'MAH-123', 'name' => 'Oil Filter', 'mpn' => 'OC 123'],
            'commercial' => ['cost' => '1.234,56 EUR', 'qty' => '1.250', 'status' => 'LAGERND'],
            'media' => ['images' => 'a.jpg|b.jpg'],
            'applications' => 'Toyota Hilux;Land Cruiser',
            'references' => [
                'oe' => [['make' => 'Toyota', 'number' => '90915-YZZD2', 'scheme' => 'OE']],
                'iam' => 'ALT-1|ALT-2',
                'cross' => [['brand' => 'MANN-FILTER', 'number' => 'W 68/3', 'scheme' => 'MPN']],
                'supersessions' => [['number' => 'OC 456', 'brand' => 'MAHLE', 'direction' => 'superseded_by']],
            ],
        ], 'sftp://supplier/catalog.csv');

        $this->assertNotNull($record);
        $this->assertSame('MAH-123', $record->externalId);
        $this->assertSame('OC 123', $record->manufacturerPartNumber);
        $this->assertSame(1234.56, $record->costPrice);
        $this->assertSame(1250, $record->stockQuantity);
        $this->assertSame('in_stock', $record->stockStatus);
        $this->assertSame(['a.jpg', 'b.jpg'], $record->images);
        $this->assertSame(['Toyota Hilux', 'Land Cruiser'], $record->fitments);
        $this->assertSame([['make' => 'Toyota', 'number' => '90915-YZZD2', 'scheme' => 'OE']], $record->oeNumbers);
        $this->assertSame(['ALT-1', 'ALT-2'], $record->iamNumbers);
        $this->assertSame('W 68/3', $record->crossReferences[0]['number']);
        $this->assertSame('OC 456', $record->supersessions[0]['number']);
        $this->assertSame('EUR', $record->currency);
        $this->assertSame('sftp://supplier/catalog.csv', $record->sourceUrl);
    }

    public function test_it_rejects_rows_without_stable_identity_or_name(): void
    {
        $supplier = new Supplier(['default_currency' => 'EUR']);

        $this->assertNull((new SupplierRecordMapper)->map($supplier, ['external_id' => '', 'name' => 'Filter']));
        $this->assertNull((new SupplierRecordMapper)->map($supplier, ['external_id' => 'A1', 'name' => '']));
    }
}
