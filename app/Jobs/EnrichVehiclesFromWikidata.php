<?php

namespace App\Jobs;

use App\Catalog\Enrichment\Wikidata\WikidataRateLimited;
use App\Catalog\Enrichment\Wikidata\WikidataVehicleEnricher;
use App\Enums\CatalogImportStatus;
use App\Models\CatalogImportRun;
use App\Models\VehicleGeneration;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class EnrichVehiclesFromWikidata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 8;

    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(public readonly int $runId, public readonly int $batchSize = 25)
    {
        $this->onQueue('catalog-enrichment');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("wikidata-enrichment:{$this->runId}"))->expireAfter(1200)];
    }

    public function handle(WikidataVehicleEnricher $enricher): void
    {
        $run = CatalogImportRun::query()->with('source')->findOrFail($this->runId);
        if (in_array($run->status, [CatalogImportStatus::Completed, CatalogImportStatus::Cancelled], true)) {
            return;
        }

        $source = $run->source;
        $checkpoint = $run->checkpoint ?? [];
        $types = array_values($checkpoint['types'] ?? ['vehicle_make', 'vehicle_model']);
        $typeIndex = (int) ($checkpoint['type_index'] ?? 0);
        $lastId = (int) ($checkpoint['last_id'] ?? 0);

        while (isset($types[$typeIndex])) {
            $entityType = (string) $types[$typeIndex];
            $entities = $this->query($entityType)->where('id', '>', $lastId)->orderBy('id')->limit($this->batchSize)->get();
            if ($entities->isEmpty()) {
                $typeIndex++;
                $lastId = 0;
                $run->update(['checkpoint' => ['types' => $types, 'type_index' => $typeIndex, 'last_id' => 0]]);

                continue;
            }

            foreach ($entities as $entity) {
                try {
                    $result = $enricher->enrich($source, $run, $entityType, $entity);
                    $run->increment('fetched_count');
                    $run->increment('parsed_count');
                    if ($result['status'] === 'published') {
                        $run->increment('matched_count');
                        $run->increment('published_count');
                    } else {
                        $run->increment('skipped_count');
                    }
                } catch (WikidataRateLimited $exception) {
                    $run->update(['checkpoint' => ['types' => $types, 'type_index' => $typeIndex, 'last_id' => $lastId]]);
                    $this->release($exception->retryAfterSeconds);

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    $run->increment('failed_count');
                }

                $lastId = (int) $entity->getKey();
                $run->update(['checkpoint' => ['types' => $types, 'type_index' => $typeIndex, 'last_id' => $lastId]]);
            }

            self::dispatch($run->id, $this->batchSize);

            return;
        }

        $run->refresh()->update([
            'status' => $run->failed_count > 0 ? CatalogImportStatus::CompletedWithErrors : CatalogImportStatus::Completed,
            'summary' => [
                'entity_types' => $types,
                'note' => 'Wikidata is used as enrichment/provenance; fitment is never inferred from aliases.',
            ],
            'finished_at' => now(),
        ]);
        $source->update(['last_successful_sync_at' => now()]);
    }

    private function query(string $entityType): Builder
    {
        return match ($entityType) {
            'vehicle_make' => VehicleMake::query()->where('is_active', true),
            'vehicle_model' => VehicleModel::query()->with('make')->where('is_active', true),
            'vehicle_generation' => VehicleGeneration::query()->with('model.make'),
            default => throw new \InvalidArgumentException("Unsupported Wikidata enrichment entity type: {$entityType}."),
        };
    }

    public function failed(Throwable $exception): void
    {
        CatalogImportRun::query()->whereKey($this->runId)->update([
            'status' => CatalogImportStatus::Failed,
            'error_message' => Str::limit($exception->getMessage(), 4000),
            'finished_at' => now(),
        ]);
    }
}
