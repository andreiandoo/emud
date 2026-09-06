<?php

namespace Tests\Unit\Suppliers;

use App\Models\Supplier;
use App\Suppliers\Connectors\SftpFeedConnector;
use App\Suppliers\Parsing\StructuredSupplierFeedParser;
use App\Suppliers\Parsing\SupplierRecordMapper;
use App\Suppliers\Transport\SftpSupplierFilesystemFactory;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SftpFeedConnectorTest extends TestCase
{
    public function test_it_streams_maps_and_hashes_a_remote_feed(): void
    {
        Storage::fake('supplier-sftp-test');
        $disk = Storage::disk('supplier-sftp-test');
        $contents = "id,name,mpn,price\n1,Oil Filter,OC-123,12.50\n2,Air Filter,LX-456,18.75\n";
        $disk->put('exports/catalog.csv', $contents);

        $factory = Mockery::mock(SftpSupplierFilesystemFactory::class);
        $factory->shouldReceive('build')->once()->andReturn($disk);

        $supplier = new Supplier([
            'code' => 'TEST',
            'catalog_endpoint' => 'exports/catalog.csv',
            'default_currency' => 'EUR',
            'field_mapping' => [
                'external_id' => 'id',
                'name' => 'name',
                'manufacturer_part_number' => 'mpn',
                'cost_price' => 'price',
            ],
            'settings' => ['feed_format' => 'csv'],
        ]);

        $connector = new SftpFeedConnector($factory, new StructuredSupplierFeedParser, new SupplierRecordMapper);
        $records = iterator_to_array($connector->records($supplier, 'catalog'), false);
        $artifact = $connector->lastArtifact();

        $this->assertCount(2, $records);
        $this->assertSame('OC-123', $records[0]->manufacturerPartNumber);
        $this->assertSame(12.5, $records[0]->costPrice);
        $this->assertNotNull($artifact);
        $this->assertSame('exports/catalog.csv', $artifact['source_path']);
        $this->assertSame(hash('sha256', $contents), $artifact['checksum_sha256']);
        $this->assertSame(strlen($contents), $artifact['size_bytes']);
        $this->assertSame('csv', $artifact['metadata']['format']);
    }

    public function test_it_rejects_remote_path_traversal_before_connecting(): void
    {
        $factory = Mockery::mock(SftpSupplierFilesystemFactory::class);
        $factory->shouldNotReceive('build');
        $supplier = new Supplier([
            'code' => 'TEST',
            'catalog_endpoint' => '../private/catalog.csv',
            'default_currency' => 'EUR',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('path traversal');

        iterator_to_array((new SftpFeedConnector($factory, new StructuredSupplierFeedParser, new SupplierRecordMapper))->records($supplier, 'catalog'));
    }
}
