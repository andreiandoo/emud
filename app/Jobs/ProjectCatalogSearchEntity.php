<?php

namespace App\Jobs;

use App\Catalog\Search\CatalogSearchProjector;
use App\Models\CatalogPart;
use App\Models\VehicleConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ProjectCatalogSearchEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $entityType, public readonly int $entityId)
    {
        $this->onQueue('catalog-search');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-search-entity:{$this->entityType}:{$this->entityId}"))->expireAfter(360)];
    }

    public function handle(CatalogSearchProjector $projector): void
    {
        if (! Schema::hasTable('catalog_search_documents')) {
            return;
        }

        match ($this->entityType) {
            'catalog_part' => $this->projectPart($projector),
            'vehicle_configuration' => $this->projectVehicle($projector),
            default => throw new InvalidArgumentException("Unsupported search projection entity type: {$this->entityType}."),
        };
    }

    private function projectPart(CatalogSearchProjector $projector): void
    {
        $part = CatalogPart::withTrashed()
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->find($this->entityId);

        if (! $part || $part->trashed()) {
            $projector->forget('catalog_part', $this->entityId);

            return;
        }

        $projector->projectPart($part);
    }

    private function projectVehicle(CatalogSearchProjector $projector): void
    {
        $vehicle = VehicleConfiguration::query()
            ->with(['generation.model.make', 'engine', 'identifiers.source'])
            ->find($this->entityId);

        if (! $vehicle) {
            $projector->forget('vehicle_configuration', $this->entityId);

            return;
        }

        $projector->projectVehicle($vehicle);
    }
}
