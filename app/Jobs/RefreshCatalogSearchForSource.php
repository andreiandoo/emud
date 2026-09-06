<?php

namespace App\Jobs;

use App\Catalog\Search\CatalogSearchProjector;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\VehicleAlias;
use App\Models\VehicleConfiguration;
use App\Models\VehicleIdentifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class RefreshCatalogSearchForSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $sourceId,
        public readonly string $phase = 'part_numbers',
        public readonly int $afterId = 0,
        public readonly int $batchSize = 500,
    ) {
        $this->onQueue('catalog-search');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("catalog-search-source:{$this->sourceId}:{$this->phase}:{$this->afterId}"))->expireAfter(960)];
    }

    public function handle(CatalogSearchProjector $projector): void
    {
        if (! Schema::hasTable('catalog_search_documents')) {
            return;
        }

        match ($this->phase) {
            'part_numbers' => $this->refreshParts($projector),
            'vehicle_identifiers' => $this->refreshVehicles($projector),
            'vehicle_aliases' => $this->refreshAliases(),
            default => throw new InvalidArgumentException("Unsupported source search refresh phase: {$this->phase}."),
        };
    }

    private function refreshParts(CatalogSearchProjector $projector): void
    {
        $rows = CatalogPartNumber::query()
            ->where('catalog_source_id', $this->sourceId)
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit($this->batchSize)
            ->get(['id', 'catalog_part_id']);

        $partIds = $rows->pluck('catalog_part_id')->filter()->unique()->values();
        CatalogPart::query()
            ->with(['brand', 'category', 'numbers.brand', 'numbers.oeMake', 'numbers.source'])
            ->whereIn('id', $partIds)
            ->get()
            ->each(fn ($part) => $projector->projectPart($part));

        $this->continueOrAdvance($rows, 'part_numbers', 'vehicle_identifiers');
    }

    private function refreshVehicles(CatalogSearchProjector $projector): void
    {
        $rows = VehicleIdentifier::query()
            ->where('catalog_source_id', $this->sourceId)
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit($this->batchSize)
            ->get(['id', 'configuration_id']);

        $configurationIds = $rows->pluck('configuration_id')->filter()->unique()->values();
        VehicleConfiguration::query()
            ->with(['generation.model.make', 'engine', 'identifiers.source'])
            ->whereIn('id', $configurationIds)
            ->get()
            ->each(fn ($vehicle) => $projector->projectVehicle($vehicle));

        $this->continueOrAdvance($rows, 'vehicle_identifiers', 'vehicle_aliases');
    }

    private function refreshAliases(): void
    {
        $rows = VehicleAlias::query()
            ->where('catalog_source_id', $this->sourceId)
            ->where('id', '>', $this->afterId)
            ->orderBy('id')
            ->limit($this->batchSize)
            ->get(['id', 'entity_type', 'entity_id']);

        foreach ($rows as $alias) {
            if (in_array($alias->entity_type, ['vehicle_make', 'vehicle_model', 'vehicle_generation'], true)) {
                RefreshVehicleSearchForAlias::dispatch($alias->entity_type, (int) $alias->entity_id);
            }
        }

        $this->continueOrAdvance($rows, 'vehicle_aliases', null);
    }

    private function continueOrAdvance($rows, string $currentPhase, ?string $nextPhase): void
    {
        if ($rows->count() === $this->batchSize) {
            self::dispatch($this->sourceId, $currentPhase, (int) $rows->last()->id, $this->batchSize);

            return;
        }

        if ($nextPhase !== null) {
            self::dispatch($this->sourceId, $nextPhase, 0, $this->batchSize);
        }
    }
}
