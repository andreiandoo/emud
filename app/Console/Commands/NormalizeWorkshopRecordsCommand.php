<?php

namespace App\Console\Commands;

use App\Jobs\NormalizeWorkshopRecords;
use App\Models\WorkshopDataSource;
use App\Models\WorkshopSourceRecord;
use App\Workshops\Ingestion\RecordNormalizers;
use Illuminate\Console\Command;

class NormalizeWorkshopRecordsCommand extends Command
{
    protected $signature = 'workshops:normalize
        {--source= : Only this data source key (rar_service, onrc, osm…)}
        {--status=pending : pending, failed, parsed or all}
        {--limit= : At most this many records}
        {--sync : Run here instead of queueing chunks}';

    protected $description = 'Re-read stored source records into workshops, without fetching anything.';

    public function handle(RecordNormalizers $normalizers): int
    {
        $status = (string) $this->option('status');
        $query = WorkshopSourceRecord::query()
            ->whereIn('record_type', ['authorization', 'company', 'poi'])
            ->when($status !== 'all', fn ($query) => $query->where('parse_status', $status))
            ->when($this->option('source'), function ($query, string $key): void {
                $query->where('data_source_id', WorkshopDataSource::query()->where('key', $key)->value('id') ?? 0);
            })
            ->orderBy('id')
            ->when($this->option('limit'), fn ($query, $limit) => $query->limit((int) $limit));

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No records to normalise.');

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $failed = 0;
            $bar = $this->output->createProgressBar($ids->count());

            foreach ($ids->chunk(200) as $chunk) {
                WorkshopSourceRecord::query()->with('dataSource')->whereIn('id', $chunk->all())->orderBy('id')->each(function (WorkshopSourceRecord $record) use ($normalizers, &$failed, $bar): void {
                    $failed += $normalizers->normalizeSafely($record) ? 0 : 1;
                    $bar->advance();
                });
            }

            $bar->finish();
            $this->newLine();
            $this->info($ids->count().' records read, '.$failed.' failed.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        foreach ($ids->chunk(250) as $chunk) {
            NormalizeWorkshopRecords::dispatch($chunk->values()->all());
        }

        $this->info('Queued '.$ids->count().' records on the "workshops" queue.');

        return self::SUCCESS;
    }
}
