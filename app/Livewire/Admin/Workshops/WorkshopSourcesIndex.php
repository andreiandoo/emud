<?php

namespace App\Livewire\Admin\Workshops;

use App\Models\WorkshopDataSource;
use App\Workshops\Stats\WorkshopStatistics;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Where the registry's data comes from, how fresh it is, and what is cleared for publication.
 */
#[Layout('layouts::admin')]
class WorkshopSourcesIndex extends Component
{
    public function togglePublic(int $sourceId): void
    {
        $source = WorkshopDataSource::query()->findOrFail($sourceId);
        $source->update(['is_public_output_allowed' => ! $source->is_public_output_allowed]);
    }

    public function toggleEnabled(int $sourceId): void
    {
        $source = WorkshopDataSource::query()->findOrFail($sourceId);
        $source->update(['is_enabled' => ! $source->is_enabled]);
    }

    public function render()
    {
        $statistics = app(WorkshopStatistics::class);

        return view('livewire.admin.workshops.sources', [
            'sources' => WorkshopDataSource::query()
                ->withCount([
                    'records',
                    'records as current_records_count' => fn ($query) => $query->where('is_current', true),
                    'records as failed_records_count' => fn ($query) => $query->where('parse_status', 'failed'),
                ])
                ->orderBy('key')
                ->get(),
            'overview' => $statistics->overview(),
            'counties' => $statistics->byCounty(),
            'runs' => $statistics->latestRuns(15),
        ]);
    }
}
