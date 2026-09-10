<?php

namespace App\Console\Commands;

use App\Enums\WorkshopRecordMatchStatus;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopRecordMatch;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\DataSourceCatalog;
use App\Workshops\Ingestion\RecordNormalizers;
use Illuminate\Console\Command;

class MatchOnrcCompanies extends Command
{
    protected $signature = 'workshops:onrc:match {--all : Re-match every stored ONRC company, not only the unmatched and ambiguous ones}';

    protected $description = 'Match stored ONRC companies against the registry again, without downloading anything.';

    public function handle(RecordNormalizers $normalizers): int
    {
        $source = WorkshopDataSource::query()->where('key', DataSourceCatalog::ONRC)->first();

        if ($source === null) {
            $this->warn('No ONRC data has been imported yet. Run workshops:onrc:import first.');

            return self::FAILURE;
        }

        $records = WorkshopSourceRecord::query()
            ->with('dataSource')
            ->where('data_source_id', $source->id)
            ->where('record_type', 'company')
            ->where('is_current', true)
            ->when(! $this->option('all'), fn ($query) => $query->whereHas('matches', fn ($query) => $query
                ->where('target_type', WorkshopRecordMatch::TARGET_COMPANY)
                ->whereNull('reviewed_by')
                ->whereIn('status', [WorkshopRecordMatchStatus::Unmatched, WorkshopRecordMatchStatus::Ambiguous, WorkshopRecordMatchStatus::Probable])));

        $total = 0;
        $failed = 0;

        $records->chunkById(500, function ($chunk) use ($normalizers, &$total, &$failed): void {
            foreach ($chunk as $record) {
                $total++;
                $failed += $normalizers->normalizeSafely($record) ? 0 : 1;
            }
        });

        $matched = WorkshopRecordMatch::query()->where('target_type', WorkshopRecordMatch::TARGET_COMPANY)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $this->info("{$total} ONRC records matched again, {$failed} failed.");
        $this->table(['Status', 'Records'], $matched->map(fn ($count, $status): array => [$status, $count])->values()->all());

        return self::SUCCESS;
    }
}
