<?php

namespace App\Console\Commands;

use App\Models\CatalogApiConsumer;
use App\Models\CatalogApiKey;
use App\Models\CatalogPart;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\SupplierProduct;
use App\Models\VehicleConfiguration;
use Database\Seeders\AutomotiveCatalogDemoFixtureSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedAutomotiveCatalogDemo extends Command
{
    protected $signature = 'catalog:demo:seed
        {--api-key : Issue a fresh API key for the demo consumer and print it once}
        {--rebuild-search : Reset and queue the part/vehicle search projection rebuild}';

    protected $description = 'Seed deterministic synthetic automotive catalog data for local development and API testing.';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => AutomotiveCatalogDemoFixtureSeeder::class,
            '--force' => true,
        ]);

        $consumer = CatalogApiConsumer::query()->firstOrNew(['slug' => 'catalog-demo-local']);
        if (! $consumer->exists) {
            $consumer->public_id = (string) Str::ulid();
        }
        $consumer->fill([
            'name' => 'Catalog DEMO Local',
            'plan' => 'demo',
            'monthly_quota' => 100000,
            'requests_used' => 0,
            'period_started_at' => now()->startOfMonth(),
            'is_active' => true,
            'metadata' => ['fixture' => true, 'local_development' => true],
        ])->save();

        $token = null;
        if ($this->option('api-key')) {
            $consumer->keys()
                ->where('name', 'DEMO CLI')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $token = CatalogApiKey::issue($consumer, 'DEMO CLI')['token'];
        }

        if ($this->option('rebuild-search')) {
            $this->call('catalog:search:rebuild', [
                '--entity' => 'all',
                '--reset' => true,
            ]);
        }

        $this->newLine();
        $this->info('Automotive catalog DEMO data is ready.');
        $this->warn('All DEMO-* identifiers are synthetic fixtures and are not authoritative OEM/TecDoc data.');

        $vehicles = VehicleConfiguration::query()
            ->where('commercial_name', 'like', '%DEMO%')
            ->orderBy('id')
            ->get();

        $this->table(['Vehicle ID', 'Configuration'], $vehicles->map(fn (VehicleConfiguration $vehicle) => [
            $vehicle->id,
            $vehicle->commercial_name,
        ])->all());

        $parts = CatalogPart::query()
            ->where('mpn_raw', 'like', 'DEMO-%')
            ->orderBy('mpn_raw')
            ->get();
        $this->table(['Part public ID', 'MPN', 'Name'], $parts->take(20)->map(fn (CatalogPart $part) => [
            'prt_'.$part->public_id,
            $part->mpn_raw,
            $part->name,
        ])->all());

        $hilux = $vehicles->firstWhere('commercial_name', 'Hilux 2.8 4WD DEMO');
        $liftKit = $parts->firstWhere('mpn_raw', 'DEMO-LFT-410');

        $this->line('Try part graph: GET /api/v1/parts/by-number/DEMO-FLT-100/graph?scheme=MPN&depth=3');
        $this->line('Try vehicle search: GET /api/v1/vehicles/search?q=Duster');
        if ($hilux && $liftKit) {
            $this->line('Try compatibility: POST /api/v1/compatibility/check with '.json_encode([
                'vehicle_id' => $hilux->id,
                'part_id' => 'prt_'.$liftKit->public_id,
            ]));
        }
        $this->line('QA queue: /admin/catalog-unresolved-relations');
        $this->line('Supplier mapping: /admin/catalog-supplier-matching');
        $this->line('Pending DEMO relations: '.CatalogUnresolvedPartRelation::query()->where('target_number_raw', 'like', 'DEMO-%')->where('status', 'pending')->count());
        $this->line('Unmapped DEMO supplier products: '.SupplierProduct::query()->where('external_id', 'like', 'SUP-DEMO-%')->where('catalog_mapping_status', 'unmapped')->count());

        if ($token) {
            $this->newLine();
            $this->info('DEMO API key (shown once):');
            $this->line($token);
            $this->line('Use as: X-API-Key: '.$token);
        } else {
            $this->line('Add --api-key to issue and print a fresh local API key.');
        }

        return self::SUCCESS;
    }
}
