<?php

namespace App\Console\Commands;

use App\Catalog\CollectionMatcher;
use Illuminate\Console\Command;

/**
 * Rebuilds the automatic product-to-collection links for the whole catalogue.
 *
 * Needed whenever collections change rather than products: adding a Jimny collection has to pull
 * in the four hundred parts already imported for it, and the importer only ever looks at the
 * product in front of it.
 */
class SyncProductCollections extends Command
{
    protected $signature = 'collections:sync';

    protected $description = 'Re-derive which vehicle collections each product belongs to, from its fitments.';

    public function handle(CollectionMatcher $matcher): int
    {
        $this->info('Se recalculează apartenența produselor la colecții…');

        $result = $matcher->syncAll(function (int $done): void {
            if ($done % 1000 === 0) {
                $this->line("  {$done} produse…");
            }
        });

        $this->info("Gata: {$result['products']} produse, {$result['links']} legături active.");

        return self::SUCCESS;
    }
}
