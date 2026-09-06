<?php

namespace App\Console\Commands;

use App\Enums\CatalogImportStatus;
use App\Jobs\EnrichVehiclesFromWikidata;
use App\Models\CatalogImportRun;
use App\Models\CatalogSource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EnrichCatalogFromWikidata extends Command
{
    protected $signature = 'catalog:wikidata:enrich {--types=vehicle_make,vehicle_model : Comma-separated entity types} {--batch=25 : Entities per queue job}';

    protected $description = 'Queue resumable Wikidata enrichment for canonical vehicle entities.';

    public function handle(): int
    {
        $source = CatalogSource::query()->where('code', 'WIKIDATA')->where('is_active', true)->first();
        if (! $source) {
            $this->error('Enable the WIKIDATA catalog source before starting enrichment.');

            return self::FAILURE;
        }

        $allowed = ['vehicle_make', 'vehicle_model', 'vehicle_generation'];
        $types = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('types'))))));
        if ($types === [] || array_diff($types, $allowed) !== []) {
            $this->error('Allowed types: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $batch = min(100, max(1, (int) $this->option('batch')));
        $run = CatalogImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'catalog_source_id' => $source->id,
            'mode' => 'wikidata_enrichment',
            'status' => CatalogImportStatus::Running,
            'importer_name' => 'wikidata-action-api',
            'checkpoint' => ['types' => $types, 'type_index' => 0, 'last_id' => 0],
            'started_at' => now(),
        ]);
        $source->update(['last_attempted_sync_at' => now()]);
        EnrichVehiclesFromWikidata::dispatch($run->id, $batch);

        $this->info("Queued Wikidata enrichment run {$run->uuid} for ".implode(', ', $types).'.');

        return self::SUCCESS;
    }
}
