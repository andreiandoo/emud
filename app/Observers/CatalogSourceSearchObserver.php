<?php

namespace App\Observers;

use App\Jobs\RefreshCatalogSearchForSource;
use App\Models\CatalogSource;

class CatalogSourceSearchObserver
{
    public function updated(CatalogSource $source): void
    {
        if ($source->wasChanged('allow_api_redistribution')) {
            RefreshCatalogSearchForSource::dispatch((int) $source->getKey())->afterCommit();
        }
    }
}
