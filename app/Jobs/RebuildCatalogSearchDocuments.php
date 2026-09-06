<?php

namespace App\Jobs;

use App\Catalog\Search\CatalogSearchProjector;
use App\Models\CatalogPart;
use App\Models\VehicleConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class RebuildCatalogSearchDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $entityType,
        public readonly int $afterId = 0,
        public readonly int $batchSize = 1000,
    ) {
        $this->onQueue('catalog-search');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-search:{$this->entityType}:{$this->afterId}"))->expireAfter(2100)];
    }

    public function handle(CatalogSearchProjector $projector): void
    {
        $models = $this->query()->where('id', '>', $this->afterId)->orderBy('id')->limit($this->batchSize)->get();
        foreach ($models as $model) {
            if ($this->entityType === 'catalog_part') {
                $projector->projectPart($model);
            } else {
                $projector->projectVehicle($model);
            }
        }

        if ($models->count() === $this->batchSize) {
            self::dispatch($this->entityType, (int) $models->last()->getKey(), $this->batchSize);
        }
    }

    private function query(): Builder
    {
        return match ($this->entityType) {
            'catalog_part' => CatalogPart::query()->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source']),
            'vehicle_configuration' => VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers.source']),
            default => throw new InvalidArgumentException("Unsupported search projection entity type: {$this->entityType}."),
        };
    }
}
