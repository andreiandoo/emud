<?php

namespace App\Catalog\Canonicalization;

use App\Catalog\Canonicalization\Contracts\CatalogRecordCanonicalizer;
use App\Catalog\Canonicalization\Vehicles\EeaVehicleCanonicalizer;
use App\Catalog\Canonicalization\Vehicles\LifeOfCapoVehicleCanonicalizer;
use App\Catalog\Canonicalization\Vehicles\VpicReferenceCanonicalizer;
use App\Models\CatalogSource;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

class CatalogCanonicalizerRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(CatalogSource $source): CatalogRecordCanonicalizer
    {
        $class = $source->canonicalizer_class ?: match ($source->code) {
            'EEA' => EeaVehicleCanonicalizer::class,
            'LIFEOFCAPO' => LifeOfCapoVehicleCanonicalizer::class,
            'VPIC' => VpicReferenceCanonicalizer::class,
            default => throw new RuntimeException("No canonicalizer configured for {$source->code}."),
        };

        $canonicalizer = $this->container->make($class);
        throw_unless($canonicalizer instanceof CatalogRecordCanonicalizer, InvalidArgumentException::class, "{$class} must implement CatalogRecordCanonicalizer.");

        return $canonicalizer;
    }
}
