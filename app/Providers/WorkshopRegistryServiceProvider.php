<?php

namespace App\Providers;

use App\Workshops\Classification\KeywordServiceClassifier;
use App\Workshops\Classification\ServiceTaxonomy;
use App\Workshops\Contracts\Geocoder;
use App\Workshops\Contracts\ServiceClassifier;
use App\Workshops\Contracts\WebSearchProvider;
use App\Workshops\Geocoding\NominatimGeocoder;
use App\Workshops\Geocoding\NullGeocoder;
use App\Workshops\Sources\Rar\RarNomenclature;
use App\Workshops\Web\BraveWebSearchProvider;
use App\Workshops\Web\NullWebSearchProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the workshop registry's replaceable parts. Geocoding and web search are optional and
 * default to implementations that do nothing, so the pipeline runs without any external key.
 */
class WorkshopRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: a queue worker lives for hours, and a nomenclature refreshed
        // or a service type added between two jobs must be seen by the next one.
        $this->app->scoped(RarNomenclature::class);
        $this->app->scoped(ServiceTaxonomy::class);

        $this->app->bind(Geocoder::class, fn ($app): Geocoder => match (config('workshops.geocoder.driver')) {
            'nominatim' => $app->make(NominatimGeocoder::class),
            default => new NullGeocoder,
        });

        $this->app->bind(WebSearchProvider::class, fn ($app): WebSearchProvider => match (config('workshops.web.search_provider')) {
            'brave' => filled(config('workshops.web.brave_api_key')) ? $app->make(BraveWebSearchProvider::class) : new NullWebSearchProvider,
            default => new NullWebSearchProvider,
        });

        $this->app->bind(ServiceClassifier::class, KeywordServiceClassifier::class);
    }
}
