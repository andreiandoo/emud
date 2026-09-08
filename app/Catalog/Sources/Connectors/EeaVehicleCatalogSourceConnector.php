<?php

namespace App\Catalog\Sources\Connectors;

use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Catalog\Sources\Contracts\CatalogSourceReleaseProvider;
use App\Catalog\Sources\Contracts\ResumableCatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EeaVehicleCatalogSourceConnector implements CatalogSourceConnector, CatalogSourceReleaseProvider, ResumableCatalogSourceConnector
{
    /**
     * Alias => Discodata column. The alias is what normalizeRow() and the canonicalizer read,
     * so it stays stable even when a dataset does not expose the underlying column.
     */
    private const PROJECTION = [
        'Mk' => 'Mk',
        'Cn' => 'Cn',
        'Tan' => 'Tan',
        'T' => 'T',
        'Va' => 'Va',
        'Ve' => 'Ve',
        'Ct' => 'Ct',
        'Cr' => 'Cr',
        'Ft' => 'Ft',
        'Fm' => 'Fm',
        'ec' => 'ec (cm3)',
        'ep' => 'ep (KW)',
        'mass_kg' => 'm (kg)',
        'test_mass_kg' => 'Mt',
        'co2_wltp' => 'Ewltp (g/km)',
        'co2_nedc' => 'Enedc (g/km)',
        'electric_consumption_wh_km' => 'z (Wh/km)',
        'eco_reduction_wltp' => 'Erwltp (g/km)',
        'year' => 'year',
    ];

    /**
     * Dropping any of these would change record identity or make the row unusable, so their
     * absence is a hard error rather than a silently reduced projection.
     */
    private const REQUIRED_ALIASES = ['Mk', 'Cn', 'year'];

    /**
     * Identifies the way the crawl is split into pages. Whole-table OFFSET paging and
     * per-manufacturer paging reach different rows through the same page number, so a
     * checkpoint written by one must not be resumed by the other.
     */
    private const CHECKPOINT_SCHEME = 'by-manufacturer/v1';

    /** @var array<string, list<string>> Probed column support, keyed by table. */
    private array $supportedColumns = [];

    /** @var array<string, list<string>> Manufacturer partitions, keyed by table and filters. */
    private array $manufacturers = [];

    private int $datasetIndex = 0;

    private string $manufacturer = '';

    private int $page = 1;

    private string $fingerprint = '';

    /** @return iterable<array<string, mixed>> */
    public function records(CatalogSource $source, string $mode = 'catalog'): iterable
    {
        $datasets = $this->datasets($source, $mode);
        $fingerprint = $this->fingerprintFor($datasets);

        // A checkpoint taken against a different dataset list, release or paging scheme must
        // not be trusted: resuming at a position that meant something else would skip records.
        if ($this->fingerprint !== $fingerprint) {
            $this->datasetIndex = 0;
            $this->manufacturer = '';
            $this->page = 1;
        }

        $this->fingerprint = $fingerprint;
        $resumeIndex = $this->datasetIndex;
        $resumeManufacturer = $this->manufacturer;
        $resumePage = $this->page;

        foreach (array_values($datasets) as $index => $dataset) {
            if ($index < $resumeIndex) {
                continue;
            }

            $this->datasetIndex = $index;
            $manufacturers = $this->manufacturers($source, $dataset);
            $resumeAt = $index === $resumeIndex ? $resumeManufacturer : '';
            $position = $resumeAt === '' ? false : array_search($resumeAt, $manufacturers, true);

            // A manufacturer that has vanished from the feed leaves the checkpoint pointing at
            // nothing. Restarting the dataset re-fetches rows that are already staged, which
            // upserts harmlessly; guessing a position would silently skip everything before it.
            foreach (array_slice($manufacturers, $position === false ? 0 : $position) as $offset => $manufacturer) {
                $this->manufacturer = $manufacturer;
                $startPage = $offset === 0 && $manufacturer === $resumeAt ? max(1, $resumePage) : 1;

                foreach ($this->datasetRecords($source, $dataset, $manufacturer, $startPage) as $record) {
                    yield $record;
                }
            }
        }
    }

    public function resumeFrom(array $checkpoint): void
    {
        $this->fingerprint = (string) ($checkpoint['fingerprint'] ?? '');
        $this->datasetIndex = max(0, (int) ($checkpoint['dataset_index'] ?? 0));
        $this->manufacturer = trim((string) ($checkpoint['manufacturer'] ?? ''));
        $this->page = max(1, (int) ($checkpoint['page'] ?? 1));
    }

    public function checkpoint(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'dataset_index' => $this->datasetIndex,
            'manufacturer' => $this->manufacturer,
            'page' => $this->page,
        ];
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
            // Probe the projection the import will really run. Testing only [Mk] and [Cn] made
            // a dataset look healthy here and then fail hours into an import on a column the
            // table does not have.
            $projection = $this->projectionFor($source, $dataset);
            $query = $this->configurationQuery($source, $dataset);

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
                'columns' => count($projection),
                'unavailable_columns' => array_values(array_diff(array_keys(self::PROJECTION), array_keys($projection))),
                // How many partitions the import will be split into, which is the single best
                // predictor of how long it will take and whether it fits in one job window.
                'manufacturers' => count($this->manufacturers($source, $dataset)),
            ];
        }

        return [
            'ok' => collect($results)->every(fn (array $result): bool => $result['ok']),
            'status' => 200,
            'datasets' => $results,
        ];
    }

    /**
     * Splitting the crawl by manufacturer is what keeps every page shallow. Paging the whole
     * table with OFFSET made Discodata re-sort the full DISTINCT result for each page: measured
     * 22s per 1,000 rows at page 1,099 of the 2025 cars table, against 1-2s once the WHERE
     * narrows to a single manufacturer. Those slow pages sat close enough to Discodata's own
     * query timeout that one of them anywhere in the crawl failed the entire run.
     *
     * @return list<string>
     */
    private function manufacturers(CatalogSource $source, array $dataset): array
    {
        $table = $this->validatedTable((string) $dataset['table']);
        $filters = $this->datasetFilters($dataset);
        $cacheKey = $table.'|'.implode(' AND ', $filters);

        if (array_key_exists($cacheKey, $this->manufacturers)) {
            return $this->manufacturers[$cacheKey];
        }

        $query = 'SELECT DISTINCT [Mk] AS [Mk] FROM '.$table.' WHERE '.implode(' AND ', $filters);
        $pageSize = $this->pageSize($source);
        $page = 1;
        $names = [];

        while (true) {
            $rows = $this->fetchRows($source, $query, $page, $pageSize);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $name = trim((string) ($row['Mk'] ?? ''));

                // Discodata's collation is case-insensitive, so [Mk] = 'Ford' also returns the
                // 'FORD' rows. Keeping both spellings as partitions would fetch each of them
                // twice for no extra records.
                if ($name !== '') {
                    $names[mb_strtoupper($name)] ??= $name;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }

            $page++;
        }

        $names = array_values($names);

        // Discodata's ordering is an implementation detail of DISTINCT. Sorting here is what
        // makes a manufacturer's position reproducible across runs, and therefore what makes a
        // checkpoint mean "everything before this name is done" on the next attempt.
        sort($names, SORT_STRING);

        return $this->manufacturers[$cacheKey] = $names;
    }

    /** @return iterable<array<string, mixed>> */
    private function datasetRecords(CatalogSource $source, array $dataset, string $manufacturer, int $startPage = 1): iterable
    {
        $page = max(1, $startPage);
        $this->page = $page;
        $pageSize = $this->pageSize($source);
        $query = $this->configurationQuery($source, $dataset, $manufacturer);

        while (true) {
            $rows = $this->fetchRows($source, $query, $page, $pageSize);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
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
            // Recorded only after the page has been fully yielded, so a checkpoint always
            // points at work that still needs doing rather than skipping a partial page.
            $this->page = $page;
        }
    }

    /**
     * Discodata reports both invalid SQL and its own query timeout as HTTP 200 with an "errors"
     * key, so the HTTP client's retry never sees either. A timeout is transient and worth
     * another attempt; a rejected query is not, and retrying it would only delay the failure.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRows(CatalogSource $source, string $query, int $page, int $pageSize): array
    {
        $attempts = max(1, (int) ($source->settings['transient_retry_times'] ?? 3));
        $sleepMs = max(0, (int) ($source->settings['transient_retry_sleep_ms'] ?? 5000));

        for ($attempt = 1; ; $attempt++) {
            $payload = $this->request($source)->get($this->apiUrl($source), [
                'query' => $query,
                'p' => $page,
                'nrOfHits' => $pageSize,
            ])->throw()->json();

            $error = trim((string) (data_get($payload, 'errors.0.error') ?? ''));

            if ($error === '') {
                $rows = data_get($payload, 'results', []);

                return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
            }

            if ($attempt >= $attempts || ! $this->isTransientError($error)) {
                throw new RuntimeException('EEA Discodata error: '.$error);
            }

            usleep($sleepMs * 1000);
        }
    }

    private function isTransientError(string $error): bool
    {
        $error = mb_strtolower($error);

        return str_contains($error, 'timed out')
            || str_contains($error, 'timeout')
            || str_contains($error, 'deadlock');
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

    private function configurationQuery(CatalogSource $source, array $dataset, ?string $manufacturer = null): string
    {
        $table = $this->validatedTable((string) $dataset['table']);
        $projection = $this->projectionFor($source, $dataset);

        $select = 'SELECT DISTINCT '.implode(', ', array_map(
            static fn (string $column, string $alias): string => "[{$column}] AS [{$alias}]",
            $projection,
            array_keys($projection),
        ));

        $where = $this->datasetFilters($dataset);
        if ($manufacturer !== null && $manufacturer !== '') {
            $where[] = "[Mk] = '".$this->quote($manufacturer)."'";
        }

        return $select.' FROM '.$table.' WHERE '.implode(' AND ', $where);
    }

    /**
     * Shared by the manufacturer listing and the record query so a partition can never be
     * built from a wider set of rows than the one it is then asked to page through.
     *
     * @return list<string>
     */
    private function datasetFilters(array $dataset): array
    {
        // LEN() rather than IS NOT NULL: normalizeRow() drops rows with a blank make or model
        // anyway, and excluding them server-side keeps them out of the paging entirely.
        $filters = ['LEN([Mk]) > 0', 'LEN([Cn]) > 0'];

        $year = isset($dataset['year']) ? (int) $dataset['year'] : null;
        if ($year !== null && $year > 0) {
            $filters[] = '[year] = '.$year;
        }

        $status = trim((string) ($dataset['status'] ?? ''));
        if ($status !== '') {
            $filters[] = "[Status] = '".$this->quote($status)."'";
        }

        return $filters;
    }

    /**
     * EEA datasets do not share a column set: the 2025 vans table has no [Mt] (test mass)
     * while the cars table does, and selecting it fails the whole query with "Invalid column
     * name". Probing keeps a dataset importable with a reduced projection instead of failing,
     * and keeps working when EEA changes columns in a future release.
     *
     * @return array<string, string> alias => column
     */
    private function projectionFor(CatalogSource $source, array $dataset): array
    {
        $excluded = array_map('strval', (array) ($dataset['exclude_columns'] ?? []));
        $projection = array_filter(
            self::PROJECTION,
            static fn (string $column, string $alias): bool => ! in_array($alias, $excluded, true)
                && ! in_array($column, $excluded, true),
            ARRAY_FILTER_USE_BOTH,
        );

        $table = $this->validatedTable((string) $dataset['table']);

        foreach ($this->unsupportedColumns($source, $table, $projection) as $alias) {
            if (in_array($alias, self::REQUIRED_ALIASES, true)) {
                throw new RuntimeException(sprintf(
                    'EEA table %s is missing required column [%s].',
                    $dataset['table'],
                    $projection[$alias],
                ));
            }

            unset($projection[$alias]);
        }

        return $projection;
    }

    /**
     * One combined probe in the common case; only when that fails does each column get tested
     * individually to find which ones the table actually lacks.
     *
     * @param  array<string, string>  $projection
     * @return list<string> aliases whose column is absent
     */
    private function unsupportedColumns(CatalogSource $source, string $table, array $projection): array
    {
        if (array_key_exists($table, $this->supportedColumns)) {
            return array_values(array_diff(array_keys($projection), $this->supportedColumns[$table]));
        }

        $all = 'SELECT TOP 1 '.implode(', ', array_map(static fn (string $c): string => "[{$c}]", $projection))." FROM {$table}";

        if ($this->querySucceeds($source, $all)) {
            $this->supportedColumns[$table] = array_keys($projection);

            return [];
        }

        $supported = [];
        $missing = [];

        foreach ($projection as $alias => $column) {
            if ($this->querySucceeds($source, "SELECT TOP 1 [{$column}] FROM {$table}")) {
                $supported[] = $alias;
            } else {
                $missing[] = $alias;
            }
        }

        $this->supportedColumns[$table] = $supported;

        return $missing;
    }

    /**
     * Discodata reports invalid SQL as HTTP 200 with an "errors" key, so the status code alone
     * cannot tell a rejected query from a successful one.
     */
    private function querySucceeds(CatalogSource $source, string $query): bool
    {
        $response = $this->request($source)->get($this->apiUrl($source), [
            'query' => $query,
            'p' => 1,
            'nrOfHits' => 1,
        ]);

        if (! $response->successful()) {
            return false;
        }

        return data_get($response->json(), 'errors.0.error') === null;
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

    /**
     * Identifies the dataset list and paging scheme a checkpoint was taken against, so a
     * resumed import can tell that it is continuing the same crawl rather than jumping into
     * the middle of a different one.
     *
     * @param  array<int, array<string, mixed>>  $datasets
     */
    private function fingerprintFor(array $datasets): string
    {
        $identity = array_map(static fn (array $dataset): array => [
            'kind' => $dataset['kind'] ?? null,
            'table' => $dataset['table'] ?? null,
            'year' => $dataset['year'] ?? null,
            'status' => $dataset['status'] ?? null,
        ], array_values($datasets));

        return hash('sha256', json_encode([self::CHECKPOINT_SCHEME, $identity], JSON_THROW_ON_ERROR));
    }

    private function pageSize(CatalogSource $source): int
    {
        return max(1, min(5000, (int) ($source->settings['page_size'] ?? 5000)));
    }

    private function validatedTable(string $table): string
    {
        if (! preg_match('/^\[CO2Emission\]\.\[(?:latest|v\d+(?:r\d+)?)\]\.\[co2(?:cars|vans)(?:_[A-Za-z0-9]+)?\]$/', $table)) {
            throw new RuntimeException("Invalid EEA Discodata table identifier: {$table}");
        }

        return $table;
    }

    private function quote(string $value): string
    {
        return str_replace("'", "''", $value);
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
