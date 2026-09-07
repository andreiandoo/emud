<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CatalogSystemCheck extends Command
{
    protected $signature = 'catalog:system:check {--json : Emit machine-readable JSON}';

    protected $description = 'Check whether the automotive catalog database, search, sources and QA queues are ready to use.';

    /**
     * Every catalog/supplier job routes itself to one of these queues, in worker priority order.
     * No pipeline job uses the default queue, so a worker started without an explicit --queue
     * list processes nothing at all. Keep in sync with docker-compose.yml and the runbook;
     * CatalogPipelineQueueTest fails if a job introduces a queue that is missing here.
     */
    public const PIPELINE_QUEUES = [
        'notifications',
        'catalog-search',
        'catalog-canonicalization',
        'catalog-matching',
        'catalog-enrichment',
        'catalog-imports',
        'imports',
    ];

    /**
     * Longest $timeout declared by a pipeline job. The queue connection's retry_after must
     * exceed it: Redis releases a reserved job back to the queue after retry_after seconds
     * even while it is still running, so a lower value makes a long import restart on a
     * second worker and write to staging concurrently with the first.
     * CatalogPipelineQueueTest fails if a job declares a longer timeout than this.
     */
    public const MAX_JOB_TIMEOUT_SECONDS = 7200;

    public function handle(): int
    {
        $checks = [];
        $fatal = false;

        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $checks['database'] = ['status' => 'ok', 'message' => "Connected using {$driver}."];
        } catch (Throwable $exception) {
            $checks['database'] = ['status' => 'fail', 'message' => $exception->getMessage()];
            $this->output($checks, []);

            return self::FAILURE;
        }

        $requiredTables = [
            'catalog_sources',
            'catalog_source_records',
            'catalog_source_assertions',
            'vehicle_configurations',
            'catalog_parts',
            'catalog_part_numbers',
            'catalog_fitments',
            'catalog_part_relations',
            'catalog_search_documents',
            'supplier_products',
        ];
        $missing = collect($requiredTables)->reject(fn (string $table) => Schema::hasTable($table))->values()->all();
        if ($missing === []) {
            $checks['schema'] = ['status' => 'ok', 'message' => 'All core catalog tables are present.'];
        } else {
            $checks['schema'] = ['status' => 'fail', 'message' => 'Missing tables: '.implode(', ', $missing)];
            $fatal = true;
        }

        if ($driver === 'pgsql') {
            try {
                $installed = (bool) (DB::selectOne("select exists(select 1 from pg_extension where extname = 'pg_trgm') as installed")->installed ?? false);
                $checks['pg_trgm'] = [
                    'status' => $installed ? 'ok' : 'warn',
                    'message' => $installed ? 'pg_trgm extension is installed.' : 'pg_trgm is missing; fuzzy/trigram search will be degraded.',
                ];
            } catch (Throwable $exception) {
                $checks['pg_trgm'] = ['status' => 'warn', 'message' => 'Could not inspect pg_trgm: '.$exception->getMessage()];
            }
        } else {
            $checks['postgresql'] = ['status' => 'warn', 'message' => "Current driver is {$driver}; production catalog is designed for PostgreSQL."];
        }

        $stats = $this->stats();
        $checks['sources'] = [
            'status' => ($stats['catalog_sources'] ?? 0) > 0 ? 'ok' : 'warn',
            'message' => ($stats['catalog_sources'] ?? 0).' catalog source(s), '.($stats['active_sources'] ?? 0).' active, '.($stats['enabled_source_schedules'] ?? 0).' enabled schedule(s).',
        ];
        // Taxonomy sources (LIFEOFCAPO) only ever create makes/models/generations, so judging
        // this check on parts and configurations alone reports a successful import as empty.
        $canonical = ($stats['catalog_parts'] ?? 0)
            + ($stats['vehicle_configurations'] ?? 0)
            + ($stats['vehicle_makes'] ?? 0)
            + ($stats['vehicle_models'] ?? 0)
            + ($stats['vehicle_generations'] ?? 0);

        $checks['catalog'] = [
            'status' => $canonical > 0 ? 'ok' : 'warn',
            'message' => ($stats['vehicle_makes'] ?? 0).' make(s), '.($stats['vehicle_models'] ?? 0).' model(s), '.($stats['vehicle_generations'] ?? 0).' generation(s), '
                .($stats['vehicle_configurations'] ?? 0).' configuration(s), '.($stats['catalog_parts'] ?? 0).' part(s), '.($stats['catalog_fitments'] ?? 0).' fitment(s).',
        ];
        // The projector only emits documents for parts and vehicle configurations; generations
        // appear inside a configuration document rather than as documents of their own. With a
        // taxonomy-only catalog there is legitimately nothing to project, so an empty
        // projection is only a problem once projectable entities exist.
        $projectable = ($stats['catalog_parts'] ?? 0) + ($stats['vehicle_configurations'] ?? 0);
        $documents = $stats['search_documents'] ?? 0;

        $checks['search'] = match (true) {
            $documents > 0 => ['status' => 'ok', 'message' => $documents.' search projection document(s).'],
            $projectable === 0 => ['status' => 'ok', 'message' => 'No documents yet, and nothing to project: the projection covers parts and vehicle configurations, and the catalog holds neither.'],
            default => ['status' => 'warn', 'message' => $projectable.' projectable entity/entities but 0 document(s). Run catalog:search:rebuild --reset and confirm a worker covers catalog-search.'],
        };
        $checks['qa'] = [
            'status' => 'ok',
            'message' => ($stats['open_conflicts'] ?? 0).' open conflict(s), '.($stats['pending_relations'] ?? 0).' unresolved relation(s), '.($stats['unmatched_supplier_products'] ?? 0).' unmatched supplier product(s).',
        ];
        $checks['runtime'] = [
            'status' => 'ok',
            'message' => 'Queue='.config('queue.default').' (default queue "'.config('queue.connections.'.config('queue.default').'.queue').'" is unused by the pipeline); cache='.config('cache.default').'. Workers must cover: '.implode(',', self::PIPELINE_QUEUES).'.',
        ];
        $checks['imports'] = $this->lastImportRunCheck($stats);
        $checks['queue_retry'] = $this->queueRetryCheck();
        $checks['demo'] = [
            'status' => ($stats['demo_parts'] ?? 0) > 0 ? 'ok' : 'warn',
            'message' => ($stats['demo_parts'] ?? 0) > 0
                ? ($stats['demo_parts'].' DEMO part(s) available for smoke testing.')
                : 'No DEMO fixture found. Run: php artisan catalog:demo:seed --api-key --rebuild-search',
        ];

        $this->output($checks, $stats);

        return $fatal ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Canonical counts alone cannot tell a queued job that never ran from an import that
     * failed, or from records that reached staging but were never canonicalized. Report the
     * last run and the staging depth so the failing stage is identifiable without SQL.
     *
     * @param  array<string, int>  $stats
     * @return array{status:string,message:string}
     */
    private function lastImportRunCheck(array $stats): array
    {
        if (! Schema::hasTable('catalog_import_runs')) {
            return ['status' => 'warn', 'message' => 'catalog_import_runs table is missing; run migrations.'];
        }

        $staged = $stats['staged_source_records'] ?? 0;

        $run = DB::table('catalog_import_runs')
            ->leftJoin('catalog_sources', 'catalog_sources.id', '=', 'catalog_import_runs.catalog_source_id')
            ->orderByDesc('catalog_import_runs.id')
            ->select([
                'catalog_sources.code',
                'catalog_import_runs.mode',
                'catalog_import_runs.status',
                'catalog_import_runs.fetched_count',
                'catalog_import_runs.parsed_count',
                'catalog_import_runs.failed_count',
                'catalog_import_runs.error_message',
                'catalog_import_runs.started_at',
                'catalog_import_runs.updated_at',
            ])
            ->first();

        if ($run === null) {
            return [
                'status' => 'warn',
                'message' => 'No import run recorded. A queued job that never starts means no worker covers catalog-imports; check the queue depth and the worker --queue list.',
            ];
        }

        $summary = sprintf(
            'Last run: %s %s, status=%s, fetched=%d parsed=%d failed=%d, staged records=%d.',
            $run->code ?? 'unknown source',
            $run->mode,
            $run->status,
            (int) $run->fetched_count,
            (int) $run->parsed_count,
            (int) $run->failed_count,
            $staged
        );

        if ($run->error_message !== null && $run->error_message !== '') {
            return ['status' => 'warn', 'message' => $summary.' Error: '.Str::limit((string) $run->error_message, 160)];
        }

        if (in_array($run->status, ['pending', 'running'], true)) {
            // A job killed by its timeout never reaches the failure handler, so the row keeps
            // reporting "running". The import touches the row every 500 records, so a stopped
            // heartbeat is what separates a dead run from a slow one.
            $silent = $run->updated_at === null
                ? 0
                : Carbon::now()->getTimestamp() - Carbon::parse((string) $run->updated_at)->getTimestamp();

            if ($silent > 600) {
                return ['status' => 'warn', 'message' => $summary.sprintf(' No progress for %d minute(s): the run says running but the job is almost certainly dead. Re-queue it; a resumable source continues from its checkpoint.', intdiv($silent, 60))];
            }

            return ['status' => 'warn', 'message' => $summary.' Still in progress or waiting for a worker.'];
        }

        $canonical = ($stats['catalog_parts'] ?? 0)
            + ($stats['vehicle_configurations'] ?? 0)
            + ($stats['vehicle_makes'] ?? 0)
            + ($stats['vehicle_models'] ?? 0)
            + ($stats['vehicle_generations'] ?? 0);

        if ($staged > 0 && $canonical === 0) {
            return ['status' => 'warn', 'message' => $summary.' Records reached staging but nothing was canonicalized; check the catalog-canonicalization queue.'];
        }

        return ['status' => 'ok', 'message' => $summary];
    }

    /**
     * A reserved job that outlives retry_after is handed to another worker while the first
     * is still importing, so the same source is ingested twice in parallel and nothing in
     * the logs identifies the duplication as a configuration problem.
     *
     * @return array{status:string,message:string}
     */
    private function queueRetryCheck(): array
    {
        $connection = (string) config('queue.default');
        $retryAfter = config('queue.connections.'.$connection.'.retry_after');

        if ($retryAfter === null) {
            return ['status' => 'ok', 'message' => "Connection {$connection} does not reserve jobs; retry_after does not apply."];
        }

        $retryAfter = (int) $retryAfter;

        if ($retryAfter > self::MAX_JOB_TIMEOUT_SECONDS) {
            return ['status' => 'ok', 'message' => "retry_after={$retryAfter}s exceeds the longest job timeout (".self::MAX_JOB_TIMEOUT_SECONDS.'s).'];
        }

        return [
            'status' => 'warn',
            'message' => "retry_after={$retryAfter}s is below the longest job timeout (".self::MAX_JOB_TIMEOUT_SECONDS.'s): a long import is released mid-run and imported twice. Set '.strtoupper($connection).'_QUEUE_RETRY_AFTER='.(self::MAX_JOB_TIMEOUT_SECONDS + 60).'.',
        ];
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        return [
            'catalog_sources' => $this->count('catalog_sources'),
            'active_sources' => $this->countWhere('catalog_sources', 'is_active', true),
            'enabled_source_schedules' => $this->countWhere('catalog_source_schedules', 'is_enabled', true),
            'catalog_parts' => $this->count('catalog_parts'),
            'vehicle_makes' => $this->count('vehicle_makes'),
            'vehicle_models' => $this->count('vehicle_models'),
            'vehicle_generations' => $this->count('vehicle_generations'),
            'vehicle_configurations' => $this->count('vehicle_configurations'),
            'catalog_fitments' => $this->count('catalog_fitments'),
            'staged_source_records' => $this->count('catalog_source_records'),
            'search_documents' => $this->count('catalog_search_documents'),
            'open_conflicts' => $this->countWhere('catalog_conflicts', 'status', 'open'),
            'pending_relations' => $this->countWhere('catalog_unresolved_part_relations', 'status', 'pending'),
            'unmatched_supplier_products' => $this->countWhere('supplier_products', 'catalog_mapping_status', 'unmatched') + $this->countWhere('supplier_products', 'catalog_mapping_status', 'unmapped'),
            'demo_parts' => Schema::hasTable('catalog_parts') ? DB::table('catalog_parts')->where('mpn_raw', 'like', 'DEMO-%')->count() : 0,
        ];
    }

    private function count(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function countWhere(string $table, string $column, mixed $value): int
    {
        return Schema::hasTable($table) ? DB::table($table)->where($column, $value)->count() : 0;
    }

    /** @param array<string, array{status:string,message:string}> $checks @param array<string, int> $stats */
    private function output(array $checks, array $stats): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['checks' => $checks, 'stats' => $stats], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->table(['Check', 'Status', 'Details'], collect($checks)->map(
            fn (array $check, string $name) => [$name, strtoupper($check['status']), $check['message']],
        )->values()->all());

        if ($stats !== []) {
            $this->newLine();
            $this->line('If catalog/search data is empty: php artisan catalog:demo:seed --api-key --rebuild-search');
            $this->line('For scheduled imports: php artisan queue:work --queue='.implode(',', self::PIPELINE_QUEUES));
            $this->line('and php artisan schedule:work (or production cron + supervised queue workers on the same queue list).');
        }
    }
}
