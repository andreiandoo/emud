<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $checks['catalog'] = [
            'status' => (($stats['catalog_parts'] ?? 0) + ($stats['vehicle_configurations'] ?? 0)) > 0 ? 'ok' : 'warn',
            'message' => ($stats['catalog_parts'] ?? 0).' part(s), '.($stats['vehicle_configurations'] ?? 0).' vehicle configuration(s), '.($stats['catalog_fitments'] ?? 0).' fitment(s).',
        ];
        $checks['search'] = [
            'status' => ($stats['search_documents'] ?? 0) > 0 ? 'ok' : 'warn',
            'message' => ($stats['search_documents'] ?? 0).' search projection document(s).',
        ];
        $checks['qa'] = [
            'status' => 'ok',
            'message' => ($stats['open_conflicts'] ?? 0).' open conflict(s), '.($stats['pending_relations'] ?? 0).' unresolved relation(s), '.($stats['unmatched_supplier_products'] ?? 0).' unmatched supplier product(s).',
        ];
        $checks['runtime'] = [
            'status' => 'ok',
            'message' => 'Queue='.config('queue.default').' (default queue "'.config('queue.connections.'.config('queue.default').'.queue').'" is unused by the pipeline); cache='.config('cache.default').'. Workers must cover: '.implode(',', self::PIPELINE_QUEUES).'.',
        ];
        $checks['demo'] = [
            'status' => ($stats['demo_parts'] ?? 0) > 0 ? 'ok' : 'warn',
            'message' => ($stats['demo_parts'] ?? 0) > 0
                ? ($stats['demo_parts'].' DEMO part(s) available for smoke testing.')
                : 'No DEMO fixture found. Run: php artisan catalog:demo:seed --api-key --rebuild-search',
        ];

        $this->output($checks, $stats);

        return $fatal ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        return [
            'catalog_sources' => $this->count('catalog_sources'),
            'active_sources' => $this->countWhere('catalog_sources', 'is_active', true),
            'enabled_source_schedules' => $this->countWhere('catalog_source_schedules', 'is_enabled', true),
            'catalog_parts' => $this->count('catalog_parts'),
            'vehicle_configurations' => $this->count('vehicle_configurations'),
            'catalog_fitments' => $this->count('catalog_fitments'),
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
