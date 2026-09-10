<?php

namespace App\Console\Commands;

use App\Catalog\CollectionMatcher;
use App\Models\CustomerVehicle;
use App\Models\VehicleCollection;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Rebuilds the automatic product-to-collection links for the whole catalogue.
 *
 * Needed whenever collections change rather than products: adding a Jimny collection has to pull
 * in the four hundred parts already imported for it, and the importer only ever looks at the one
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

        $vehicles = $this->linkGarageVehicles();
        $this->info("Mașini din garaje legate de o colecție: {$vehicles}.");

        return self::SUCCESS;
    }

    /**
     * Cars already saved in a garage before their collection existed.
     *
     * Only the ones with no collection yet, so a car an operator repointed by hand stays where
     * they put it.
     */
    private function linkGarageVehicles(): int
    {
        $linked = 0;

        CustomerVehicle::query()
            ->whereNull('vehicle_collection_id')
            ->orderBy('id')
            ->chunkById(500, function (EloquentCollection $vehicles) use (&$linked): void {
                foreach ($vehicles as $vehicle) {
                    $collection = VehicleCollection::forVehicle($vehicle->make_id, $vehicle->model_id, $vehicle->generation_id);

                    if ($collection !== null) {
                        $vehicle->update(['vehicle_collection_id' => $collection->id]);
                        $linked++;
                    }
                }
            });

        return $linked;
    }
}
