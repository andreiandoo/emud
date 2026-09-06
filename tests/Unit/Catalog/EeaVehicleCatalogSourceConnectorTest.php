<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Sources\Connectors\EeaVehicleCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EeaVehicleCatalogSourceConnectorTest extends TestCase
{
    public function test_it_streams_distinct_car_configurations_with_release_provenance(): void
    {
        Http::fake([
            'https://discodata.eea.europa.eu/sql*' => Http::sequence()
                ->push([
                    'results' => [
                        [
                            'Mk' => 'LAND ROVER',
                            'Cn' => 'DISCOVERY SPORT',
                            'Tan' => 'E11*2007/46*4317*12',
                            'T' => 'LC',
                            'Va' => 'A5',
                            'Ve' => 'ABCD',
                            'Ct' => 'M1',
                            'Cr' => 'M1',
                            'Ft' => 'diesel',
                            'Fm' => 'M',
                            'ec' => 1999,
                            'ep' => 132,
                            'mass_kg' => 1900,
                            'test_mass_kg' => 2050,
                            'co2_wltp' => 194,
                            'co2_nedc' => null,
                            'electric_consumption_wh_km' => null,
                            'eco_reduction_wltp' => null,
                            'year' => 2025,
                        ],
                        [
                            'Mk' => 'TOYOTA',
                            'Cn' => 'LAND CRUISER',
                            'Tan' => 'E6*2018/858*00001*01',
                            'T' => 'J25',
                            'Va' => 'A',
                            'Ve' => '1',
                            'Ct' => 'M1',
                            'Cr' => 'M1',
                            'Ft' => 'diesel',
                            'Fm' => 'M',
                            'ec' => 2755,
                            'ep' => 150,
                            'year' => 2025,
                        ],
                    ],
                ])
                ->push(['results' => []]),
        ]);

        $source = $this->source([
            [
                'kind' => 'cars',
                'table' => '[CO2Emission].[latest].[co2cars_2025Pv31]',
                'year' => 2025,
                'status' => 'P',
            ],
        ], pageSize: 2);
        $connector = new EeaVehicleCatalogSourceConnector;

        $records = iterator_to_array($connector->records($source, 'cars'));
        $release = $connector->release($source, 'cars');

        $this->assertCount(2, $records);
        $this->assertSame('vehicle_configuration', $records[0]['record_type']);
        $this->assertSame(2025, $records[0]['registration_year']);
        $this->assertSame('cars', $records[0]['source_vehicle_kind']);
        $this->assertSame('[CO2Emission].[latest].[co2cars_2025Pv31]', $records[0]['source_table']);
        $this->assertStringStartsWith('eea:', $records[0]['external_id']);
        $this->assertStringStartsWith('eea:2025P:', $release['release_key']);

        Http::assertSent(function ($request): bool {
            $url = urldecode($request->url());

            return str_contains($url, 'SELECT DISTINCT')
                && str_contains($url, '[CO2Emission].[latest].[co2cars_2025Pv31]')
                && str_contains($url, 'nrOfHits=2');
        });
    }

    public function test_catalog_mode_reads_both_passenger_car_and_van_datasets(): void
    {
        Http::fake([
            'https://discodata.eea.europa.eu/sql*' => Http::sequence()
                ->push(['results' => [[
                    'Mk' => 'JEEP',
                    'Cn' => 'WRANGLER',
                    'T' => 'JL',
                    'Ft' => 'petrol',
                    'ec' => 1995,
                    'ep' => 200,
                    'year' => 2025,
                ]]])
                ->push(['results' => [[
                    'Mk' => 'FORD',
                    'Cn' => 'RANGER',
                    'T' => '2AB',
                    'Ft' => 'diesel',
                    'ec' => 1996,
                    'ep' => 125,
                    'year' => 2025,
                ]]]),
        ]);

        $source = $this->source([
            ['kind' => 'cars', 'table' => '[CO2Emission].[latest].[co2cars_2025Pv31]', 'year' => 2025, 'status' => 'P'],
            ['kind' => 'vans', 'table' => '[CO2Emission].[latest].[co2vans_2025Pv27]', 'year' => 2025, 'status' => 'P'],
        ]);

        $records = iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));

        $this->assertCount(2, $records);
        $this->assertSame('cars', $records[0]['source_vehicle_kind']);
        $this->assertSame('vans', $records[1]['source_vehicle_kind']);
    }

    public function test_it_rejects_untrusted_table_identifiers(): void
    {
        $source = $this->source([
            ['kind' => 'cars', 'table' => '[OtherDb].[dbo].[users]; DROP TABLE users--', 'year' => 2025],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid EEA Discodata table identifier');

        iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));
    }

    private function source(array $datasets, int $pageSize = 1000): CatalogSource
    {
        return new CatalogSource([
            'code' => 'EEA',
            'base_url' => 'https://www.eea.europa.eu/en/datahub',
            'settings' => [
                'api_url' => 'https://discodata.eea.europa.eu/sql',
                'release_label' => '2025P',
                'dataset_published_at' => '2026-06-25',
                'datasets' => $datasets,
                'page_size' => $pageSize,
            ],
        ]);
    }
}
