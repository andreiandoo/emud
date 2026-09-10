<?php

namespace App\Console\Commands;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\RecordNormalizers;
use Illuminate\Console\Command;

class MatchOsmWorkshops extends Command
{
    protected $signature = 'workshops:osm:match {--all : Re-match every OSM point, not only the ones still waiting}';

    protected $description = 'Match stored OpenStreetMap points against the registry again (after a RAR import, for instance).';

    public function handle(RecordNormalizers $normalizers): int
    {
        $source = WorkshopDataSource::query()->where('key', DataSourceCatalog::OSM)->first();

        if ($source === null) {
            $this->warn('No OpenStreetMap data has been imported yet. Run workshops:osm:import first.');

            return self::FAILURE;
        }

        $records = WorkshopSourceRecord::query()
            ->with('dataSource')
            ->where('data_source_id', $source->id)
            ->where('record_type', 'poi')
            ->where('is_current', true)
            ->when(! $this->option('all'), fn ($query) => $query->whereDoesntHave('link')->whereHas('matches', fn ($query) => $query
                ->where('target_type', WorkshopRecordMatch::TARGET_WORKSHOP)
                ->whereNull('reviewed_by')
                ->whereIn('status', [WorkshopRecordMatchStatus::Probable, WorkshopRecordMatchStatus::Ambiguous])));

        $total = 0;
        $failed = 0;

        $records->chunkById(500, function ($chunk) use ($normalizers, &$total, &$failed): void {
            foreach ($chunk as $record) {
                // Re-matching means deciding again: an automatic decision is discarded first.
                WorkshopRecordMatch::query()->where('source_record_id', $record->id)->whereNull('reviewed_by')->delete();
                $total++;
                $failed += $normalizers->normalizeSafely($record) ? 0 : 1;
            }
        });

        $this->info("{$total} OpenStreetMap points matched again, {$failed} failed.");
        $this->table(['Status', 'Points'], WorkshopRecordMatch::query()
            ->where('target_type', WorkshopRecordMatch::TARGET_WORKSHOP)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count, $status): array => [$status, $count])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
