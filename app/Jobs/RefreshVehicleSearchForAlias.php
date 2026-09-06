<?php

namespace App\Jobs;

use App\Catalog\Search\CatalogSearchProjector;
use App\Models\VehicleConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class RefreshVehicleSearchForAlias implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $aliasEntityType,
        public readonly int $aliasEntityId,
        public readonly int $afterId = 0,
        public readonly int $batchSize = 500,
    ) {
        $this->onQueue('catalog-search');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-search-alias:{$this->aliasEntityType}:{$this->aliasEntityId}:{$this->afterId}"))->expireAfter(960)];
    }

    public function handle(CatalogSearchProjector $projector): void
    {
        if (! Schema::hasTable('catalog_search_documents')) {
            return;
        }

        $vehicles = $this->query()
            ->where('vehicle_configurations.id', '>', $this->afterId)
            ->orderBy('vehicle_configurations.id')
            ->limit($this->batchSize)
            ->get();

        foreach ($vehicles as $vehicle) {
            $projector->projectVehicle($vehicle);
        }

        if ($vehicles->count() === $this->batchSize) {
            self::dispatch(
                $this->aliasEntityType,
                $this->aliasEntityId,
                (int) $vehicles->last()->getKey(),
                $this->batchSize,
            );
        }
    }

    private function query(): Builder
    {
        $query = VehicleConfiguration::query()->with(['generation.model.make', 'engine', 'identifiers.source']);

        return match ($this->aliasEntityType) {
            'vehicle_make' => $query->whereHas('generation.model', fn ($model) => $model->where('make_id', $this->aliasEntityId)),
            'vehicle_model' => $query->whereHas('generation', fn ($generation) => $generation->where('model_id', $this->aliasEntityId)),
            'vehicle_generation' => $query->where('generation_id', $this->aliasEntityId),
            default => throw new InvalidArgumentException("Unsupported vehicle alias entity type: {$this->aliasEntityType}."),
        };
    }
}
