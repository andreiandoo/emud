<?php

namespace App\Observers;

use App\Catalog\Api\CatalogVersion;
use Illuminate\Database\Eloquent\Model;

/**
 * Retire every cached public response as soon as anything behind one changes.
 *
 * Registered for the entities the public API reads and, importantly, for the rows that decide
 * whether it may read them at all — a source losing redistribution rights or an assertion being
 * withdrawn has to take the cached pages with it, or the API keeps serving data whose permission
 * has just been revoked.
 */
class CatalogVersionObserver
{
    public function saved(Model $model): void
    {
        CatalogVersion::bump();
    }

    public function deleted(Model $model): void
    {
        CatalogVersion::bump();
    }

    public function restored(Model $model): void
    {
        CatalogVersion::bump();
    }
}
