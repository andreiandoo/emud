<?php

namespace App\Suppliers;

use App\Models\Supplier;
use App\Suppliers\Connectors\HttpFeedConnector;
use App\Suppliers\Contracts\SupplierConnector;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ConnectorRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(Supplier $supplier): SupplierConnector
    {
        $class = $supplier->connector_class ?: HttpFeedConnector::class;
        $connector = $this->container->make($class);

        throw_unless($connector instanceof SupplierConnector, InvalidArgumentException::class, "{$class} must implement SupplierConnector.");

        return $connector;
    }
}
