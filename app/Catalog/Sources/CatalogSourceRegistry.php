<?php

namespace App\Catalog\Sources;

use App\Catalog\Sources\Connectors\HttpCatalogSourceConnector;
use App\Catalog\Sources\Contracts\CatalogSourceConnector;
use App\Models\CatalogSource;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class CatalogSourceRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(CatalogSource $source): CatalogSourceConnector
    {
        $class = $source->connector_class ?: HttpCatalogSourceConnector::class;
        $connector = $this->container->make($class);

        throw_unless($connector instanceof CatalogSourceConnector, InvalidArgumentException::class, "{$class} must implement CatalogSourceConnector.");

        return $connector;
    }
}
