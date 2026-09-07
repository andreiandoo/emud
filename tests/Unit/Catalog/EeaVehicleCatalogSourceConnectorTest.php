<?php

namespace Tests\Unit\Catalog;

use App\Catalog\Sources\Connectors\EeaVehicleCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EeaVehicleCatalogSourceConnectorTest extends TestCase
{
    public function test_it_streams_distinct_car_configurations_with_release_provenance(): void
    {
        $this->fakeDiscodata(rows: [
            'cars' => [[
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
                    'year' => 2025,
                ],
                [
                    'Mk' => 'TOYOTA',
                    'Cn' => 'LAND CRUISER',
                    'Tan' => 'E6*2018/858*00001*01',
                    'T' => 'J25',
                    'Va' => 'A',
                    'Ve' => '1',
                    'Ft' => 'diesel',
                    'ec' => 2755,
                    'ep' => 150,
                    'year' => 2025,
                ],
            ]],
        ]);

        $source = $this->source([$this->carsDataset()], pageSize: 2);
        $connector = new EeaVehicleCatalogSourceConnector;

        $records = iterator_to_array($connector->records($source, 'cars'));
        $release = $connector->release($source, 'cars');

        $this->assertCount(2, $records);
        $this->assertSame('vehicle_configuration', $records[0]['record_type']);
        $this->assertSame(2025, $records[0]['registration_year']);
        $this->assertSame('cars', $records[0]['source_vehicle_kind']);
        $this->assertStringStartsWith('eea:', $records[0]['external_id']);
        $this->assertStringStartsWith('eea:2025P:', $release['release_key']);

        Http::assertSent(function (Request $request): bool {
            $url = urldecode($request->url());

            return str_contains($url, 'SELECT DISTINCT')
                && str_contains($url, '[CO2Emission].[latest].[co2cars_2025Pv31]')
                && str_contains($url, 'nrOfHits=2');
        });
    }

    public function test_catalog_mode_reads_both_passenger_car_and_van_datasets(): void
    {
        $this->fakeDiscodata(rows: [
            'cars' => [[['Mk' => 'JEEP', 'Cn' => 'WRANGLER', 'T' => 'JL', 'Ft' => 'petrol', 'ec' => 1995, 'ep' => 200, 'year' => 2025]]],
            'vans' => [[['Mk' => 'FORD', 'Cn' => 'RANGER', 'T' => '2AB', 'Ft' => 'diesel', 'ec' => 1996, 'ep' => 125, 'year' => 2025]]],
        ]);

        $source = $this->source([$this->carsDataset(), $this->vansDataset()]);

        $records = iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));

        $this->assertCount(2, $records);
        $this->assertSame('cars', $records[0]['source_vehicle_kind']);
        $this->assertSame('vans', $records[1]['source_vehicle_kind']);
    }

    /**
     * The real 2025 vans table has no [Mt]. Selecting it fails the entire query with "Invalid
     * column name", which used to abort the whole EEA run hours into the cars dataset.
     */
    public function test_a_dataset_missing_an_optional_column_still_imports_without_it(): void
    {
        $this->fakeDiscodata(
            rows: ['vans' => [[['Mk' => 'FORD', 'Cn' => 'TRANSIT', 'T' => 'V363', 'Ft' => 'diesel', 'ec' => 1995, 'ep' => 125, 'year' => 2025]]]],
            missingColumns: ['vans' => ['Mt']],
        );

        $source = $this->source([$this->vansDataset()]);

        $records = iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));

        $this->assertCount(1, $records);
        $this->assertSame('vans', $records[0]['source_vehicle_kind']);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'SELECT DISTINCT')
            && ! str_contains(urldecode($request->url()), '[Mt]'));
    }

    public function test_a_dataset_missing_a_required_column_fails_loudly(): void
    {
        $this->fakeDiscodata(rows: [], missingColumns: ['cars' => ['Cn']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing required column [Cn]');

        iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($this->source([$this->carsDataset()])));
    }

    public function test_connection_testing_exercises_the_real_projection(): void
    {
        $this->fakeDiscodata(rows: ['vans' => [[]]], missingColumns: ['vans' => ['Mt']]);

        $result = (new EeaVehicleCatalogSourceConnector)->testConnection($this->source([$this->vansDataset()]));

        $this->assertTrue($result['ok']);
        $this->assertSame(['test_mass_kg'], $result['datasets'][0]['unavailable_columns']);
    }

    public function test_it_resumes_a_previous_attempt_at_the_recorded_page(): void
    {
        $rows = ['cars' => [[['Mk' => 'A', 'Cn' => 'A', 'year' => 2025]]]];
        $source = $this->source([$this->carsDataset()], pageSize: 1);

        // Take the fingerprint the way the job does: from a completed run's own checkpoint.
        $this->fakeDiscodata($rows);
        $probe = new EeaVehicleCatalogSourceConnector;
        iterator_to_array($probe->records($source, 'cars'));
        $fingerprint = $probe->checkpoint()['fingerprint'];

        $this->fakeDiscodata($rows);
        $connector = new EeaVehicleCatalogSourceConnector;
        $connector->resumeFrom(['fingerprint' => $fingerprint, 'dataset_index' => 0, 'page' => 7]);
        iterator_to_array($connector->records($source, 'cars'));

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'SELECT DISTINCT')
            && str_contains($request->url(), 'p=7'));
    }

    /**
     * Resuming a checkpoint taken against a different dataset list would skip records that the
     * new configuration has never fetched, so the position has to be discarded.
     */
    public function test_it_ignores_a_checkpoint_from_a_different_dataset_configuration(): void
    {
        $this->fakeDiscodata(rows: ['cars' => [[['Mk' => 'A', 'Cn' => 'A', 'year' => 2025]]]]);

        $connector = new EeaVehicleCatalogSourceConnector;
        $connector->resumeFrom(['fingerprint' => 'stale-fingerprint', 'dataset_index' => 0, 'page' => 42]);

        iterator_to_array($connector->records($this->source([$this->carsDataset()]), 'cars'));

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'SELECT DISTINCT')
            && str_contains($request->url(), 'p=1'));
    }

    public function test_it_rejects_untrusted_table_identifiers(): void
    {
        $source = $this->source([
            ['kind' => 'cars', 'table' => '[OtherDb].[dbo].[users]; DROP TABLE users--', 'year' => 2025],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid EEA Discodata table identifier');

        iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));
    }

    /**
     * Discodata answers an invalid column with HTTP 200 and an "errors" key rather than an
     * error status, so the fake has to reproduce that shape for probing to be exercised.
     *
     * @param  array<string, list<list<array<string, mixed>>>>  $rows  kind => pages of rows
     * @param  array<string, list<string>>  $missingColumns  kind => columns the table lacks
     */
    private function fakeDiscodata(array $rows, array $missingColumns = []): void
    {
        $pageCursor = [];

        Http::fake(function (Request $request) use ($rows, $missingColumns, &$pageCursor) {
            $url = urldecode($request->url());
            $kind = str_contains($url, 'co2vans') ? 'vans' : 'cars';

            foreach ($missingColumns[$kind] ?? [] as $column) {
                if (str_contains($url, "[{$column}]")) {
                    return Http::response(['errors' => [['error' => "Invalid column name '{$column}'.", 'errorcode' => 10003]]]);
                }
            }

            if (str_contains($url, 'SELECT TOP 1')) {
                return Http::response(['results' => []]);
            }

            $index = $pageCursor[$kind] ?? 0;
            $pageCursor[$kind] = $index + 1;

            return Http::response(['results' => $rows[$kind][$index] ?? []]);
        });
    }

    /** @return array<string, mixed> */
    private function carsDataset(): array
    {
        return ['kind' => 'cars', 'table' => '[CO2Emission].[latest].[co2cars_2025Pv31]', 'year' => 2025, 'status' => 'P'];
    }

    /** @return array<string, mixed> */
    private function vansDataset(): array
    {
        return ['kind' => 'vans', 'table' => '[CO2Emission].[latest].[co2vans_2025Pv27]', 'year' => 2025, 'status' => 'P'];
    }

    /** @param array<int, array<string, mixed>> $datasets */
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
