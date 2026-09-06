<?php

namespace App\Observers;

use App\Jobs\ProjectCatalogSearchEntity;
use App\Jobs\RefreshVehicleSearchForAlias;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\VehicleAlias;
use App\Models\VehicleConfiguration;
use App\Models\VehicleIdentifier;
use Illuminate\Database\Eloquent\Model;

class CatalogSearchMutationObserver
{
    public function saved(Model $model): void
    {
        $this->queueRefresh($model);
    }

    public function deleted(Model $model): void
    {
        $this->queueRefresh($model);
    }

    public function restored(Model $model): void
    {
        $this->queueRefresh($model);
    }

    private function queueRefresh(Model $model): void
    {
        match (true) {
            $model instanceof CatalogPart => ProjectCatalogSearchEntity::dispatch('catalog_part', (int) $model->getKey())->afterCommit(),
            $model instanceof CatalogPartNumber => ProjectCatalogSearchEntity::dispatch('catalog_part', (int) $model->catalog_part_id)->afterCommit(),
            $model instanceof VehicleConfiguration => ProjectCatalogSearchEntity::dispatch('vehicle_configuration', (int) $model->getKey())->afterCommit(),
            $model instanceof VehicleIdentifier => ProjectCatalogSearchEntity::dispatch('vehicle_configuration', (int) $model->configuration_id)->afterCommit(),
            $model instanceof VehicleAlias => $this->queueAliasRefresh($model),
            default => null,
        };
    }

    private function queueAliasRefresh(VehicleAlias $alias): void
    {
        if (! in_array($alias->entity_type, ['vehicle_make', 'vehicle_model', 'vehicle_generation'], true)) {
            return;
        }

        RefreshVehicleSearchForAlias::dispatch($alias->entity_type, (int) $alias->entity_id)->afterCommit();
    }
}
