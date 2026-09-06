<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EeaVehicleCatalogSourceConnector implements CatalogSourceConnector, CatalogSourceReleaseProvider
{
    /** @return iterable<array<string, mixed>> */
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        $datasets = $this->datasets($source, $mode);

        foreach ($datasets as $dataset) {
            foreach ($this->datasetRecords($source, $dataset) as $record) {
                yield $record;
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function release(CatalogSource $source, string $mode = 'catalog'): ?array
    {
        $datasets = $this->datasets($source, $mode);
        if ($datasets === []) {
            return null;
        }

        $identity = array_map(static fn (array $dataset): array => [
            'kind' => $dataset['kind'],
            'table' => $dataset['table'],
            'year' => $dataset['year'] ?? null,
            'status' => $dataset['status'] ?? null,
        ], $datasets);
        $releaseLabel = trim((string) ($source->settings['release_label'] ?? ''));
        $releaseKey = $releaseLabel !== ''
            ? 'eea:'.$releaseLabel.':'.substr(hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)), 0, 16)
            : 'eea:'.hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));

        return [
            'release_key' => $releaseKey,
            'published_at' => $source->settings['dataset_published_at'] ?? null,
            'retrieved_at' => now(),
            'raw_object_path' => $source->base_url ?: 'https://www.eea.europa.eu/en/datahub',
            'metadata' => [
                'provider' => 'European Environment Agency',
                'transport' => 'Discodata SQL-over-HTTP JSON API',
                'datasets' => $identity,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function testConnection(CatalogSource $source): array
    {
        $results = [];

        foreach ($this->datasets($source, 'catalog') as $dataset) {
            $query = sprintf('SELECT TOP 1 [Mk] AS [Mk], [Cn] AS [Cn] FROM %s', $this->validatedTable((string) $dataset['table']));
            $response = $this->request($source)->get($this->apiUrl($source), [
                'query' => $query,
                'p' => 1,
                'nrOfHits' => 1,
            ]);
            $payload = $response->throw()->json();
            $this->throwIfApiError($payload);

            $results[] = [
                'kind' => $dataset['kind'],
                'table' => $dataset['table'],
                'ok' => is_array(data_get($payload, 'results')),
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => collect($results)->every(fn (array $result): bool => $result['ok']),
            'status' => 200,
            'datasets' => $results,
        ];
    }

    /** @return iterable<array<string, mixed>> */
    private function datasetRecords(CatalogSource $source, array $dataset): iterable
    {
        $page = 1;
        $pageSize = max(1, min(5000, (int) ($source->settings['page_size'] ?? 1000)));
        $query = $this->configurationQuery($dataset);

        while (true) {
            $response = $this->request($source)->get($this->apiUrl($source), [
                'query' => $query,
                'p' => $page,
                'nrOfHits' => $pageSize,
            ]);
            $payload = $response->throw()->json();
            $this->throwIfApiError($payload);
            $rows = data_get($payload, 'results', []);

            if (! is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $normalized = $this->normalizeRow($row, $dataset);
                if ($normalized === null) {
                    continue;
                }

                yield $normalized;
            }

            if (count($rows) < $pageSize) {
                break;
            }

            $page++;
        }
    }

    /** @return array<string, mixed>|null */
    private function normalizeRow(array $row, array $dataset): ?array
    {
        $make = trim((string) ($row['Mk'] ?? ''));
        $commercialName = trim((string) ($row['Cn'] ?? ''));
        $year = (int) ($row['year'] ?? $dataset['year'] ?? 0);

        if ($make === '' || $commercialName === '' || $year < 1900) {
            return null;
        }

        $identityFields = [
            $dataset['kind'],
            $dataset['table'],
            $year,
            $make,
            $commercialName,
            $row['Tan'] ?? null,
            $row['T'] ?? null,
            $row['Va'] ?? null,
            $row['Ve'] ?? null,
            $row['Ft'] ?? null,
            $row['ec'] ?? null,
            $row['ep'] ?? null,
        ];

        return array_merge($row, [
            'record_type' => 'vehicle_configuration',
            'external_id' => 'eea:'.hash('sha256', json_encode($identityFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'year' => $year,
            'registration_year' => $year,
            'source_vehicle_kind' => $dataset['kind'],
            'source_table' => $dataset['table'],
            'source_status' => $dataset['status'] ?? null,
        ]);
    }

    private function configurationQuery(array $dataset): string
    {
        $table = $this->validatedTable((string) $dataset['table']);
        $year = isset($dataset['year']) ? (int) $dataset['year'] : null;
        $status = trim((string) ($dataset['status'] ?? ''));

        $select = <<<'SQL'
SELECT DISTINCT
    [Mk] AS [Mk],
    [Cn] AS [Cn],
    [Tan] AS [Tan],
    [T] AS [T],
    [Va] AS [Va],
    [Ve] AS [Ve],
    [Ct] AS [Ct],
    [Cr] AS [Cr],
    [Ft] AS [Ft],
    [Fm] AS [Fm],
    [ec (cm3)] AS [ec],
    [ep (KW)] AS [ep],
    [m (kg)] AS [mass_kg],
    [Mt] AS [test_mass_kg],
    [Ewltp (g/km)] AS [co2_wltp],
    [Enedc (g/km)] AS [co2_nedc],
    [z (Wh/km)] AS [electric_consumption_wh_km],
    [Erwltp (g/km)] AS [eco_reduction_wltp],
    [year] AS [year]
SQL;

        $where = ["[Mk] IS NOT NULL", "[Cn] IS NOT NULL"];
        if ($year !== null && $year > 0) {
            $where[] = '[year] = '.$year;
        }
        if ($status !== '') {
            $where[] = "[Status] = '".str_replace("'", "''", $status)."'";
        }

        return $select.' FROM '.$table.' WHERE '.implode(' AND ', $where);
    }

    /** @return array<int, array<string, mixed>> */
    private function datasets(CatalogSource $source, string $mode): array
    {
        $configured = $source->settings['datasets'] ?? [];
        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException('EEA source requires at least one configured dataset.');
        }

        $allowedKinds = match ($mode) {
            'catalog', 'vehicles' => ['cars', 'vans'],
            'cars' => ['cars'],
            'vans' => ['vans'],
            default => throw new RuntimeException("Unsupported EEA import mode: {$mode}."),
        };

        $datasets = [];
        foreach ($configured as $dataset) {
            if (! is_array($dataset)) {
                continue;
            }
            $kind = trim((string) ($dataset['kind'] ?? ''));
            $table = trim((string) ($dataset['table'] ?? ''));
            if ($kind === '' || $table === '' || ! in_array($kind, $allowedKinds, true)) {
                continue;
            }
            $this->validatedTable($table);
            $datasets[] = $dataset;
        }

        return $datasets;
    }

    private function validatedTable(string $table): string
    {
        if (! preg_match('/^\[CO2Emission\]\.\[(?:latest|v\d+(?:r\d+)?)\]\.\[co2(?:cars|vans)(?:_[A-Za-z0-9]+)?\]$/', $table)) {
            throw new RuntimeException("Invalid EEA Discodata table identifier: {$table}");
        }

        return $table;
    }

    /** @param array<string, mixed>|null $payload */
    private function throwIfApiError(?array $payload): void
    {
        $error = data_get($payload, 'errors.0.error');
        if ($error) {
            throw new RuntimeException('EEA Discodata error: '.(string) $error);
        }
    }

    private function apiUrl(CatalogSource $source): string
    {
        return (string) ($source->settings['api_url'] ?? 'https://discodata.eea.europa.eu/sql');
    }

    private function request(CatalogSource $source): PendingRequest
    {
        $settings = $source->settings ?? [];

        return Http::withHeaders([
            'User-Agent' => (string) ($settings['user_agent'] ?? 'eMUD-Automotive-Catalog/1.0'),
            'Accept' => 'application/json',
        ])->timeout((int) ($settings['timeout_seconds'] ?? 60))
            ->retry(
                (int) ($settings['retry_times'] ?? 3),
                (int) ($settings['retry_sleep_ms'] ?? 1500),
                throw: false,
            );
    }
}
