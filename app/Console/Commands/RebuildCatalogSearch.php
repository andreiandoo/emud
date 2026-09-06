<?php

namespace App\Console\Commands;

use App\Jobs\RebuildCatalogSearchDocuments;
use App\Models\CatalogSearchDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RebuildCatalogSearch extends Command
{
    protected $signature = 'catalog:search:rebuild {--entity=all : all, catalog_part or vehicle_configuration} {--chunk=1000 : Projection batch size} {--reset : Delete existing documents for selected entity types first}';

    protected $description = 'Queue a rebuild of denormalized automotive catalog search documents.';

    public function handle(): int
    {
        if (! Schema::hasTable('catalog_search_documents')) {
            $this->error('Run migrations before rebuilding the catalog search projection.');

            return self::FAILURE;
        }

        $entity = (string) $this->option('entity');
        $types = $entity === 'all' ? ['catalog_part', 'vehicle_configuration'] : [$entity];
        if (array_diff($types, ['catalog_part', 'vehicle_configuration']) !== []) {
            $this->error('Allowed entity values: all, catalog_part, vehicle_configuration.');

            return self::FAILURE;
        }

        $chunk = min(5000, max(50, (int) $this->option('chunk')));
        if ($this->option('reset')) {
            CatalogSearchDocument::query()->whereIn('entity_type', $types)->delete();
        }

        foreach ($types as $type) {
            RebuildCatalogSearchDocuments::dispatch($type, 0, $chunk);
            $this->line("Queued search projection for {$type} (chunk {$chunk}).");
        }

        return self::SUCCESS;
    }
}
