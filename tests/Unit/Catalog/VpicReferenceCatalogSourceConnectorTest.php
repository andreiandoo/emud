<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Sources\Connectors\VpicReferenceCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpicReferenceCatalogSourceConnectorTest extends TestCase
{
    public function test_catalog_streams_manufacturers_makes_and_models(): void
    {
        Http::fake([
            'https://vpic.nhtsa.dot.gov/api/vehicles/GetAllManufacturers*' => Http::response([
                'Count' => 2,
                'Results' => [
                    ['Mfr_ID' => 988, 'Mfr_Name' => 'AMERICAN HONDA MOTOR CO., INC.', 'Country' => 'UNITED STATES'],
                    ['Mfr_ID' => 1055, 'Mfr_Name' => 'TOYOTA MOTOR NORTH AMERICA, INC.', 'Country' => 'UNITED STATES'],
                ],
            ]),
            'https://vpic.nhtsa.dot.gov/api/vehicles/GetAllMakes*' => Http::response([
                'Count' => 2,
                'Results' => [
                    ['Make_ID' => 474, 'Make_Name' => 'HONDA'],
                    ['Make_ID' => 448, 'Make_Name' => 'TOYOTA'],
                ],
            ]),
            'https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeId/0*' => Http::response([
                'Count' => 2,
                'Results' => [
                    ['Make_ID' => 474, 'Make_Name' => 'Honda', 'Model_ID' => 1861, 'Model_Name' => 'Pilot'],
                    ['Make_ID' => 448, 'Make_Name' => 'Toyota', 'Model_ID' => 2208, 'Model_Name' => 'Land Cruiser'],
                ],
            ]),
        ]);

        $connector = new VpicReferenceCatalogSourceConnector;
        $records = iterator_to_array($connector->records($this->source(), 'catalog'), false);
        $release = $connector->release($this->source(), 'catalog');

        $this->assertCount(6, $records);
        $this->assertSame('vpic_manufacturer', $records[0]['record_type']);
        $this->assertSame('vpic:manufacturer:988', $records[0]['external_id']);
        $this->assertSame('vpic_make', $records[2]['record_type']);
        $this->assertSame(474, $records[2]['vpic_make_id']);
        $this->assertSame('vpic_model', $records[4]['record_type']);
        $this->assertSame(1861, $records[4]['vpic_model_id']);
        $this->assertStringStartsWith('vpic-api:', $release['release_key']);
    }

    public function test_it_rejects_non_nhtsa_api_hosts(): void
    {
        $source = $this->source(['api_base_url' => 'https://example.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vPIC API base URL must be');

        iterator_to_array((new VpicReferenceCatalogSourceConnector)->records($source, 'makes'));
    }

    private function source(array $settings = []): CatalogSource
    {
        return new CatalogSource([
            'code' => 'VPIC',
            'base_url' => 'https://vpic.nhtsa.dot.gov/',
            'settings' => array_merge([
                'api_base_url' => 'https://vpic.nhtsa.dot.gov',
                'reference_request_interval_ms' => 0,
            ], $settings),
        ]);
    }
}
