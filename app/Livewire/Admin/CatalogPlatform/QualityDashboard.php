<?php

namespace App\Livewire\Admin\CatalogPlatform;

use App\Models\CatalogConflict;
use App\Models\CatalogFitment;
use App\Models\CatalogPart;
use App\Models\CatalogPartNumber;
use App\Models\CatalogSource;
use App\Models\CatalogSourceRecord;
use App\Models\CatalogUnresolvedPartRelation;
use App\Models\VehicleConfiguration;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class QualityDashboard extends Component
{
    private const CACHE_KEY = 'catalog:quality-dashboard:v2';

    public function refreshMetrics(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function render()
    {
        $metrics = Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function (): array {
            $parts = CatalogPart::query()->count();
            $vehicles = VehicleConfiguration::query()->count();
            $fitments = CatalogFitment::query()->count();
            $numbers = CatalogPartNumber::query()->count();
            $partsWithoutFitments = CatalogPart::query()->doesntHave('fitments')->count();
            $partsWithoutNumbers = CatalogPart::query()->doesntHave('numbers')->count();
            $vehiclesWithoutFitments = VehicleConfiguration::query()->doesntHave('catalogFitments')->count();
            $candidateFitments = CatalogFitment::query()->where('status', 'candidate')->count();
            $lowConfidenceFitments = CatalogFitment::query()
                ->where(fn ($query) => $query->whereNull('confidence')->orWhere('confidence', '<', 70))
                ->count();
            $openConflicts = CatalogConflict::query()->where('status', 'open')->count();
            $pendingRelations = CatalogUnresolvedPartRelation::query()->where('status', 'pending')->count();
            $stalePendingRelations = CatalogUnresolvedPartRelation::query()
                ->where('status', 'pending')
                ->where('resolution_attempts', '>=', 3)
                ->count();
            $backlogStatuses = ['unprocessed', 'candidate', 'ambiguous', 'failed'];
            $backlogByStatus = CatalogSourceRecord::query()
                ->whereIn('mapping_status', $backlogStatuses)
                ->selectRaw('mapping_status, count(*) as aggregate')
                ->groupBy('mapping_status')
                ->pluck('aggregate', 'mapping_status')
                ->map(fn ($count) => (int) $count)
                ->all();

            return [
                'parts' => $parts,
                'vehicles' => $vehicles,
                'fitments' => $fitments,
                'numbers' => $numbers,
                'parts_without_fitments' => $partsWithoutFitments,
                'parts_without_numbers' => $partsWithoutNumbers,
                'vehicles_without_fitments' => $vehiclesWithoutFitments,
                'candidate_fitments' => $candidateFitments,
                'low_confidence_fitments' => $lowConfidenceFitments,
                'open_conflicts' => $openConflicts,
                'pending_relations' => $pendingRelations,
                'stale_pending_relations' => $stalePendingRelations,
                'part_fitment_coverage' => $this->percent($parts - $partsWithoutFitments, $parts),
                'vehicle_fitment_coverage' => $this->percent($vehicles - $vehiclesWithoutFitments, $vehicles),
                'backlog_by_status' => $backlogByStatus,
                'generated_at' => now(),
            ];
        });

        $sources = CatalogSource::query()
            ->withCount([
                'records',
                'records as backlog_records_count' => fn ($query) => $query->whereIn('mapping_status', ['unprocessed', 'candidate', 'ambiguous', 'failed']),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.catalog-platform.quality-dashboard', compact('metrics', 'sources'));
    }

    private function percent(int $covered, int $total): float
    {
        return $total > 0 ? round(($covered / $total) * 100, 1) : 0.0;
    }
}
