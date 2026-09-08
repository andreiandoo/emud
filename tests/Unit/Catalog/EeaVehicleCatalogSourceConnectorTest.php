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
            'cars' => [
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
            ],
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

        Http::assertSent(fn (Request $request): bool => str_contains($this->queryOf($request), 'SELECT DISTINCT')
            && str_contains($this->queryOf($request), '[CO2Emission].[latest].[co2cars_2025Pv31]')
            && str_contains($this->queryOf($request), "[Mk] = 'LAND ROVER'"));
    }

    /**
     * Manufacturers are the unit of work, so the crawl has to cover all of them and each one's
     * own pages. Paging the whole table instead made Discodata re-sort millions of rows per
     * page, which is what put the import beyond its job timeout.
     */
    public function test_it_pages_each_manufacturer_separately(): void
    {
        $this->fakeDiscodata(rows: [
            'cars' => [
                ['Mk' => 'BMW', 'Cn' => 'X3', 'T' => 'G01', 'year' => 2025],
                ['Mk' => 'BMW', 'Cn' => 'X5', 'T' => 'G05', 'year' => 2025],
                ['Mk' => 'BMW', 'Cn' => 'X7', 'T' => 'G07', 'year' => 2025],
                ['Mk' => 'AUDI', 'Cn' => 'Q5', 'T' => 'FY', 'year' => 2025],
            ],
        ]);

        $records = iterator_to_array(
            (new EeaVehicleCatalogSourceConnector)->records($this->source([$this->carsDataset()], pageSize: 2), 'cars')
        );

        // Sorted partitions, so AUDI is crawled before BMW regardless of the order EEA returns.
        $this->assertSame(['AUDI', 'BMW', 'BMW', 'BMW'], array_column($records, 'Mk'));
        $this->assertSame(['Q5', 'X3', 'X5', 'X7'], array_column($records, 'Cn'));

        // BMW does not fit in one page of two, so the second page has to be requested for it.
        Http::assertSent(fn (Request $request): bool => str_contains($this->queryOf($request), "[Mk] = 'BMW'")
            && $this->paramOf($request, 'p') === '2');
    }

    public function test_catalog_mode_reads_both_passenger_car_and_van_datasets(): void
    {
        $this->fakeDiscodata(rows: [
            'cars' => [['Mk' => 'JEEP', 'Cn' => 'WRANGLER', 'T' => 'JL', 'Ft' => 'petrol', 'ec' => 1995, 'ep' => 200, 'year' => 2025]],
            'vans' => [['Mk' => 'FORD', 'Cn' => 'RANGER', 'T' => '2AB', 'Ft' => 'diesel', 'ec' => 1996, 'ep' => 125, 'year' => 2025]],
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
            rows: ['vans' => [['Mk' => 'FORD', 'Cn' => 'TRANSIT', 'T' => 'V363', 'Ft' => 'diesel', 'ec' => 1995, 'ep' => 125, 'year' => 2025]]],
            missingColumns: ['vans' => ['Mt']],
        );

        $source = $this->source([$this->vansDataset()]);

        $records = iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($source));

        $this->assertCount(1, $records);
        $this->assertSame('vans', $records[0]['source_vehicle_kind']);

        Http::assertSent(fn (Request $request): bool => str_contains($this->queryOf($request), 'SELECT DISTINCT')
            && ! str_contains($this->queryOf($request), '[Mt]'));
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
        $this->fakeDiscodata(
            rows: ['vans' => [['Mk' => 'FORD', 'Cn' => 'TRANSIT', 'year' => 2025]]],
            missingColumns: ['vans' => ['Mt']],
        );

        $result = (new EeaVehicleCatalogSourceConnector)->testConnection($this->source([$this->vansDataset()]));

        $this->assertTrue($result['ok']);
        $this->assertSame(['test_mass_kg'], $result['datasets'][0]['unavailable_columns']);
        $this->assertSame(1, $result['datasets'][0]['manufacturers']);
    }

    public function test_it_resumes_a_previous_attempt_at_the_recorded_manufacturer_and_page(): void
    {
        $rows = ['cars' => [
            ['Mk' => 'AUDI', 'Cn' => 'Q5', 'year' => 2025],
            ['Mk' => 'BMW', 'Cn' => 'X3', 'year' => 2025],
            ['Mk' => 'BMW', 'Cn' => 'X5', 'year' => 2025],
        ]];
        $source = $this->source([$this->carsDataset()], pageSize: 1);

        // Take the fingerprint the way the job does: from a completed run's own checkpoint.
        $this->fakeDiscodata($rows);
        $probe = new EeaVehicleCatalogSourceConnector;
        iterator_to_array($probe->records($source, 'cars'));
        $fingerprint = $probe->checkpoint()['fingerprint'];

        $this->fakeDiscodata($rows);
        $connector = new EeaVehicleCatalogSourceConnector;
        $connector->resumeFrom([
            'fingerprint' => $fingerprint,
            'dataset_index' => 0,
            'manufacturer' => 'BMW',
            'page' => 2,
        ]);

        $records = iterator_to_array($connector->records($source, 'cars'));

        $this->assertSame(['X5'], array_column($records, 'Cn'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($this->queryOf($request), "[Mk] = 'AUDI'"));
    }

    /**
     * Resuming a checkpoint taken against a different dataset list — or against the older
     * whole-table paging, where page 42 meant a completely different row — would skip records
     * the new configuration has never fetched, so the position has to be discarded.
     */
    public function test_it_ignores_a_checkpoint_from_a_different_paging_scheme(): void
    {
        $this->fakeDiscodata(rows: ['cars' => [['Mk' => 'AUDI', 'Cn' => 'Q5', 'year' => 2025]]]);

        $connector = new EeaVehicleCatalogSourceConnector;
        $connector->resumeFrom(['fingerprint' => 'stale-fingerprint', 'dataset_index' => 0, 'page' => 42]);

        $records = iterator_to_array($connector->records($this->source([$this->carsDataset()]), 'cars'));

        $this->assertCount(1, $records);
        Http::assertSent(fn (Request $request): bool => str_contains($this->queryOf($request), "[Mk] = 'AUDI'")
            && $this->paramOf($request, 'p') === '1');
    }

    /**
     * Discodata reports its own query timeout as HTTP 200 with an "errors" key, so the HTTP
     * client's retry never sees it. Treating it as fatal killed multi-hour runs on a single
     * slow page.
     */
    public function test_a_query_timeout_is_retried_rather_than_failing_the_run(): void
    {
        $this->fakeDiscodata(
            rows: ['cars' => [['Mk' => 'AUDI', 'Cn' => 'Q5', 'year' => 2025]]],
            transientFailures: 2,
        );

        $records = iterator_to_array(
            (new EeaVehicleCatalogSourceConnector)->records($this->source([$this->carsDataset()]), 'cars')
        );

        $this->assertSame(['Q5'], array_column($records, 'Cn'));
    }

    public function test_a_rejected_query_still_fails_immediately(): void
    {
        $this->fakeDiscodata(rows: ['cars' => [['Mk' => 'AUDI', 'Cn' => 'Q5', 'year' => 2025]]], fatalError: 'Invalid object name.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid object name.');

        iterator_to_array((new EeaVehicleCatalogSourceConnector)->records($this->source([$this->carsDataset()]), 'cars'));
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
     * Reproduces Discodata closely enough that the connector's own paging is what is under
     * test: invalid columns and query timeouts come back as HTTP 200 with an "errors" key,
     * the manufacturer listing is a separate query, and record pages are sliced per
     * manufacturer under a case-insensitive collation.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows  kind => flat rows
     * @param  array<string, list<string>>  $missingColumns  kind => columns the table lacks
     * @param  int  $transientFailures  leading record requests answered with a query timeout
     */
    private function fakeDiscodata(
        array $rows,
        array $missingColumns = [],
        int $transientFailures = 0,
        ?string $fatalError = null,
    ): void {
        $remainingTransient = $transientFailures;

        Http::fake(function (Request $request) use ($rows, $missingColumns, $fatalError, &$remainingTransient) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
            $query = (string) ($params['query'] ?? '');
            $page = max(1, (int) ($params['p'] ?? 1));
            $size = max(1, (int) ($params['nrOfHits'] ?? 1));
            $kind = str_contains($query, 'co2vans') ? 'vans' : 'cars';

            foreach ($missingColumns[$kind] ?? [] as $column) {
                if (str_contains($query, "[{$column}]")) {
                    return Http::response(['errors' => [['error' => "Invalid column name '{$column}'.", 'errorcode' => 10003]]]);
                }
            }

            if (str_starts_with($query, 'SELECT TOP 1')) {
                return Http::response(['results' => []]);
            }

            $available = $rows[$kind] ?? [];

            if (str_starts_with($query, 'SELECT DISTINCT [Mk] AS [Mk] FROM')) {
                $names = array_values(array_unique(array_map(
                    static fn (array $row): string => (string) ($row['Mk'] ?? ''),
                    $available,
                )));

                return Http::response(['results' => array_map(
                    static fn (string $name): array => ['Mk' => $name],
                    array_slice($names, ($page - 1) * $size, $size),
                )]);
            }

            if ($fatalError !== null) {
                return Http::response(['errors' => [['error' => $fatalError, 'errorcode' => 10003]]]);
            }

            if ($remainingTransient > 0) {
                $remainingTransient--;

                return Http::response(['errors' => [['error' => 'Query timed out']]]);
            }

            $manufacturer = preg_match("/\[Mk\] = '(.*)'\$/", $query, $matches)
                ? str_replace("''", "'", $matches[1])
                : null;

            $matching = $manufacturer === null ? $available : array_values(array_filter(
                $available,
                static fn (array $row): bool => strcasecmp((string) ($row['Mk'] ?? ''), $manufacturer) === 0,
            ));

            return Http::response(['results' => array_slice($matching, ($page - 1) * $size, $size)]);
        });
    }

    private function queryOf(Request $request): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);

        return (string) ($params['query'] ?? '');
    }

    private function paramOf(Request $request, string $name): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);

        return (string) ($params[$name] ?? '');
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
                'transient_retry_sleep_ms' => 0,
            ],
        ]);
    }
}
