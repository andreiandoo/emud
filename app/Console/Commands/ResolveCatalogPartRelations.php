<?php

namespace App\Console\Commands;

use App\Catalog\Relations\CatalogPartRelationResolver;
use Illuminate\Console\Command;

class ResolveCatalogPartRelations extends Command
{
    protected $signature = 'catalog:relations:resolve {--limit=50000 : Maximum pending relations to inspect}';

    protected $description = 'Resolve pending part cross-references and supersessions when target identifiers become available.';

    public function handle(CatalogPartRelationResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $resolved = $resolver->resolvePending($limit);
        $this->info("Resolved {$resolved} pending catalog part relation(s).");

        return self::SUCCESS;
    }
}
