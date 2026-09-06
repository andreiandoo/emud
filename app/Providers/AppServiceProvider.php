<?php

namespace App\Providers;

use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\VehicleAlias;
use App\Models\VehicleConfiguration;
use App\Models\VehicleIdentifier;
use App\Observers\CatalogSearchMutationObserver;
use App\Observers\CatalogSourceSearchObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([CatalogPart::class, CatalogPartNumber::class, VehicleConfiguration::class, VehicleIdentifier::class, VehicleAlias::class] as $model) {
            $model::observe(CatalogSearchMutationObserver::class);
        }

        CatalogSource::observe(CatalogSourceSearchObserver::class);
    }
}
