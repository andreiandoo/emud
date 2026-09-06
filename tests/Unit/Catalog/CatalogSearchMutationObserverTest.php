<?php

namespace Tests\Unit\Catalog;

use App\Jobs\ProjectCatalogSearchEntity;
use App\Jobs\RefreshVehicleSearchForAlias;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\VehicleAlias;
use App\Models\VehicleConfiguration;
use App\Observers\CatalogSearchMutationObserver;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CatalogSearchMutationObserverTest extends TestCase
{
    public function test_it_queues_direct_part_and_vehicle_projection_refreshes(): void
    {
        Bus::fake();
        $observer = new CatalogSearchMutationObserver;

        $part = new CatalogPart;
        $part->setAttribute('id', 41);
        $observer->saved($part);

        $number = new CatalogPartNumber(['catalog_part_id' => 41]);
        $observer->deleted($number);

        $vehicle = new VehicleConfiguration;
        $vehicle->setAttribute('id', 77);
        $observer->saved($vehicle);

        Bus::assertDispatched(ProjectCatalogSearchEntity::class, fn ($job) => $job->entityType === 'catalog_part' && $job->entityId === 41);
        Bus::assertDispatched(ProjectCatalogSearchEntity::class, fn ($job) => $job->entityType === 'vehicle_configuration' && $job->entityId === 77);
    }

    public function test_it_fans_vehicle_alias_changes_out_to_affected_configurations(): void
    {
        Bus::fake();
        $alias = new VehicleAlias([
            'entity_type' => 'vehicle_model',
            'entity_id' => 123,
        ]);

        (new CatalogSearchMutationObserver)->saved($alias);

        Bus::assertDispatched(RefreshVehicleSearchForAlias::class, fn ($job) => $job->aliasEntityType === 'vehicle_model' && $job->aliasEntityId === 123);
    }
}
